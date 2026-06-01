<?php

namespace App\Http\Controllers;

use App\Enums\ImportBatchStatus;
use App\Http\Requests\ConfirmImportMappingRequest;
use App\Models\ImportBatch;
use App\Models\ImportMappingTemplate;
use App\Services\Import\ColumnMappingService;
use App\Services\Import\ImportBatchService;
use App\Services\Import\ImportBatchStatsService;
use App\Services\Import\ImportPipelineOrchestratorService;
use App\Services\Import\ImportPipelineReadinessService;
use App\Services\Import\ImportPipelineService;
use App\Services\Import\ImportProgressService;
use App\Services\Import\ImportChunkService;
use Illuminate\Http\JsonResponse;
use App\Services\Materialization\ImportMaterializationService;
use App\Services\Materialization\MaterializationChunkService;
use App\Services\Queue\QueueHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ImportBatchController extends Controller
{
    public function index(): View
    {
        $batches = ImportBatch::query()
            ->with('uploader')
            ->latest()
            ->paginate(20);

        return view('imports.index', compact('batches'));
    }

    public function show(
        ImportBatch $import,
        ImportBatchService $importBatchService,
        ImportBatchStatsService $batchStatsService,
        ImportMaterializationService $materializationService,
        ImportPipelineService $pipelineService,
    ): View {
        $stats = array_merge(
            $importBatchService->batchStats($import),
            $batchStatsService->rowCounts($import->id, $import),
        );
        $materialization = $materializationService->batchMaterializationStats($import);
        $materializationProgress = app(MaterializationChunkService::class)->materializationProgress($import);
        $pipeline = $pipelineService->state($import);
        $quality = $batchStatsService->cachedQuality($import);

        $readiness = app(ImportPipelineReadinessService::class);
        $pricingStatsSummary = [
            'groups' => $readiness->distinctDrugCountryGroupCount($import),
            'statistics' => $readiness->pricingStatisticsCountForBatch($import),
        ];

        $showAdvanced = (bool) config('import.show_advanced_details', false)
            || (bool) config('app.debug', false);

        return view('imports.show', [
            'batch' => $import,
            'stats' => $stats,
            'materialization' => $materialization,
            'materializationProgress' => $materializationProgress,
            'pipeline' => $pipeline,
            'ux' => $pipeline['user_experience'],
            'showAdvanced' => $showAdvanced,
            'queueHealth' => $showAdvanced ? app(QueueHealthService::class)->adminStatus() : null,
            'pricingStatsSummary' => $pricingStatsSummary,
            'qualityScore' => $quality['score'],
            'qualityRating' => $quality['rating'],
            'qualityBreakdown' => $quality['breakdown'],
        ]);
    }

    public function mapping(ImportBatch $import, ColumnMappingService $columnMapper): View|RedirectResponse
    {
        if ($import->status !== ImportBatchStatus::AwaitingMapping->value) {
            return redirect()->route('imports.show', $import);
        }

        $metadata = $import->metadata ?? [];
        $canonicalFields = config('import.canonical_fields', []);
        $headerLabels = config('import.header_labels', []);

        return view('imports.mapping', [
            'batch' => $import,
            'mappings' => $metadata['column_mappings'] ?? [],
            'detectedHeaders' => $metadata['detected_headers'] ?? [],
            'mappedHeaders' => $metadata['mapped_headers'] ?? [],
            'missingRequired' => $metadata['missing_required'] ?? [],
            'missingDrugIdentity' => $metadata['missing_drug_identity'] ?? false,
            'extraColumns' => $metadata['extra_columns'] ?? [],
            'mappingConfidence' => $metadata['mapping_confidence'] ?? 0,
            'sampleRows' => $metadata['sample_rows'] ?? [],
            'estimatedRowCount' => $metadata['estimated_row_count'] ?? 0,
            'canonicalFields' => $canonicalFields,
            'headerLabels' => $headerLabels,
            'templates' => ImportMappingTemplate::query()->latest()->limit(20)->get(),
            'canProceed' => empty($metadata['missing_required'] ?? []) && ! ($metadata['missing_drug_identity'] ?? false),
        ]);
    }

    public function confirmMapping(
        ImportBatch $import,
        ConfirmImportMappingRequest $request,
        ImportBatchService $importBatchService,
    ): RedirectResponse {
        if ($import->status !== ImportBatchStatus::AwaitingMapping->value) {
            return redirect()->route('imports.show', $import);
        }

        $userMapping = array_map('intval', array_filter(
            $request->validated('mapping'),
            fn ($index) => $index !== null && $index !== ''
        ));

        $headers = $import->metadata['detected_headers'] ?? [];
        $mappingCheck = app(ColumnMappingService::class)->detectMapping($headers, $userMapping);

        if (! $mappingCheck['can_proceed']) {
            return redirect()
                ->route('imports.mapping', $import)
                ->withErrors(['mapping' => 'Required fields are not mapped: '.implode(', ', $mappingCheck['missing_required']).($mappingCheck['missing_drug_identity'] ? ' (plus drug identity)' : '')]);
        }

        $import = $importBatchService->confirmMapping(
            $import,
            $userMapping,
            $request->validated('template_name'),
            $request->user(),
        );

        $importBatchService->dispatchImportProcessing($import);

        return redirect()
            ->route('imports.show', $import)
            ->with('success', 'Column mapping confirmed. We are preparing your data automatically — you can leave this page and return later.');
    }

    public function progress(ImportBatch $import, ImportProgressService $progressService): JsonResponse
    {
        return response()->json($progressService->snapshot($import));
    }

    public function retryFailedChunks(
        ImportBatch $import,
        ImportChunkService $chunkService,
    ): RedirectResponse {
        if (! $import->usesChunkedImport()) {
            return redirect()
                ->route('imports.show', $import)
                ->withErrors(['import' => 'This batch does not use chunked processing.']);
        }

        $retried = $chunkService->retryFailedChunks($import);

        if ($retried === 0) {
            return redirect()
                ->route('imports.show', $import)
                ->withErrors(['import' => 'No failed chunks to retry.']);
        }

        return redirect()
            ->route('imports.show', $import)
            ->with('success', "Retrying {$retried} failed chunk(s) in the background.");
    }

    public function materialize(
        ImportBatch $import,
        MaterializationChunkService $chunkService,
        ImportMaterializationService $materializationService,
    ): RedirectResponse {
        if ($import->isMaterializationRunning()) {
            return redirect()
                ->route('imports.show', $import)
                ->with('success', 'Materialization is already running in the background. Refresh this page to see progress.');
        }

        $eligible = $materializationService->batchMaterializationStats($import)['eligible_pending'];

        if ($eligible === 0) {
            return redirect()
                ->route('imports.show', $import)
                ->with('success', 'No approved rows are ready for materialization.');
        }

        $chunkService->dispatchBatchJob($import);

        return redirect()
            ->route('imports.show', $import)
            ->with('success', 'Materialization has started in the background.');
    }

    public function retryFailedMaterializationChunks(
        ImportBatch $import,
        MaterializationChunkService $chunkService,
    ): RedirectResponse {
        if (! $import->usesChunkedMaterialization()) {
            return redirect()
                ->route('imports.show', $import)
                ->withErrors(['import' => 'This batch does not use chunked materialization.']);
        }

        $retried = $chunkService->retryFailedChunks($import);

        if ($retried === 0) {
            return redirect()
                ->route('imports.show', $import)
                ->withErrors(['import' => 'No failed materialization chunks to retry.']);
        }

        return redirect()
            ->route('imports.show', $import)
            ->with('success', "Retrying {$retried} failed materialization chunk(s) in the background.");
    }

    public function preview(ImportBatch $import): View
    {
        $rows = $import->importRows()
            ->orderBy('row_number')
            ->paginate(100);

        return view('imports.preview', [
            'batch' => $import,
            'rows' => $rows,
        ]);
    }

    public function retryStatistics(
        ImportBatch $import,
        ImportPipelineOrchestratorService $orchestrator,
    ): RedirectResponse {
        $metadata = $import->metadata ?? [];

        if (($metadata['materialization_status'] ?? '') !== 'completed') {
            return redirect()
                ->route('imports.show', $import)
                ->withErrors(['statistics' => 'Market statistics can only be refreshed after data preparation completes.']);
        }

        $import->update([
            'metadata' => array_merge($metadata, [
                'pipeline_ready_at' => null,
                'pipeline_status' => 'preparing_statistics',
                'statistics_status' => 'not_started',
            ]),
        ]);

        $orchestrator->dispatchStatisticsRefresh($import->fresh());

        return redirect()
            ->route('imports.show', $import)
            ->with('success', 'Market statistics refresh has been queued.');
    }

    public function destroy(ImportBatch $import, ImportBatchService $importBatchService): RedirectResponse
    {
        $importBatchService->destroy($import);

        return redirect()
            ->route('uploads.index')
            ->with('success', 'Import batch deleted.');
    }

}
