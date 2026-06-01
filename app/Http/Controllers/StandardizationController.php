<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Services\Standardization\ImportRowStandardizationService;
use App\Services\Standardization\StandardizationChunkService;
use App\Services\Standardization\StandardizationReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StandardizationController extends Controller
{
    public function index(Request $request, StandardizationReviewService $reviewService): View
    {
        $filters = $reviewService->activeFilters($request);
        $batchReviewRequiredCount = $filters['batch']
            ? $reviewService->countBatchReviewRequired($filters['batch'])
            : 0;

        return view('standardization.index', [
            'rows' => $reviewService->paginate($filters),
            'summary' => $reviewService->summaryStats($filters),
            'batches' => $reviewService->recentBatches(),
            'filters' => $filters,
            'perPageOptions' => StandardizationReviewService::PER_PAGE_OPTIONS,
            'reviewService' => $reviewService,
            'batchReviewRequiredCount' => $batchReviewRequiredCount,
        ]);
    }

    public function runBatch(ImportBatch $batch, ImportRowStandardizationService $service): RedirectResponse
    {
        $status = $batch->metadata['standardization_status'] ?? 'not_started';

        if ($status === 'processing') {
            return redirect()
                ->route('imports.show', $batch)
                ->with('success', 'Standardization is already running in the background. Refresh this page to see progress.');
        }

        $pendingCount = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->where('standardization_status', 'pending')
            ->count();

        if ($pendingCount === 0) {
            return redirect()
                ->route('imports.show', $batch)
                ->with('success', 'No pending rows require standardization.');
        }

        $service->dispatchBatchJob($batch);

        return redirect()
            ->route('imports.show', $batch)
            ->with('success', 'Standardization has started in the background. You can refresh this page to see progress.');
    }

    public function approveRow(Request $request, ImportRow $row, ImportRowStandardizationService $service): RedirectResponse|JsonResponse
    {
        $service->approveRow($row, $request->user()?->id);

        return $this->actionResponse($request, 'Row #'.$row->row_number.' approved.');
    }

    public function rejectRow(Request $request, ImportRow $row, ImportRowStandardizationService $service): RedirectResponse|JsonResponse
    {
        $service->rejectRow($row, $request->user()?->id);

        return $this->actionResponse($request, 'Row #'.$row->row_number.' rejected.');
    }

    public function bulkAction(Request $request, ImportRowStandardizationService $service): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject,send_to_review,manual_review',
            'row_ids' => 'required|array|min:1|max:500',
            'row_ids.*' => 'integer|exists:import_rows,id',
        ]);

        $result = $service->bulkAction(
            $validated['action'],
            $validated['row_ids'],
            $request->user()?->id,
        );

        $message = sprintf(
            '%d item(s) processed%s.',
            $result['processed'],
            $result['skipped'] > 0 ? ', '.$result['skipped'].' skipped' : '',
        );

        return $this->actionResponse($request, $message, $result);
    }

    public function approveAllReviewForBatch(
        Request $request,
        ImportBatch $batch,
        ImportRowStandardizationService $service,
    ): RedirectResponse {
        $approved = $service->approveAllReviewRequiredForBatch($batch, $request->user()?->id);

        if ($approved === 0) {
            return redirect()
                ->route('imports.show', $batch)
                ->with('success', 'No review items required approval for this batch.');
        }

        return redirect()
            ->route('imports.show', $batch)
            ->with('success', sprintf('Approved %s review items successfully.', number_format($approved)));
    }

    public function editMatch(Request $request, ImportRow $row, ImportRowStandardizationService $service): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'entity' => 'required|in:drug,company',
            'standardized_drug_id' => 'required_if:entity,drug|nullable|integer|exists:standardized_drugs,id',
            'company_id' => 'required_if:entity,company|nullable|integer|exists:companies,id',
        ]);

        $service->editMatch($row, $validated, $request->user()?->id);

        return $this->actionResponse($request, 'Match updated for row #'.$row->row_number.'.');
    }

    public function searchProducts(Request $request, StandardizationReviewService $reviewService): JsonResponse
    {
        $query = $request->string('q')->toString();

        return response()->json([
            'results' => $reviewService->searchProducts($query),
        ]);
    }

    public function searchCompanies(Request $request, StandardizationReviewService $reviewService): JsonResponse
    {
        $query = $request->string('q')->toString();

        return response()->json([
            'results' => $reviewService->searchCompanies($query),
        ]);
    }

    public function retryFailedChunks(
        ImportBatch $batch,
        StandardizationChunkService $chunkService,
    ): RedirectResponse {
        if (! $batch->usesChunkedStandardization()) {
            return redirect()
                ->route('imports.show', $batch)
                ->withErrors(['standardization' => 'This batch does not use chunked standardization.']);
        }

        $retried = $chunkService->retryFailedChunks($batch);

        if ($retried === 0) {
            return redirect()
                ->route('imports.show', $batch)
                ->withErrors(['standardization' => 'No failed standardization chunks to retry.']);
        }

        return redirect()
            ->route('imports.show', $batch)
            ->with('success', "Retrying {$retried} failed standardization chunk(s) in the background.");
    }

    protected function actionResponse(Request $request, string $message, array $extra = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson() || $request->boolean('ajax')) {
            return response()->json(array_merge(['message' => $message, 'success' => true], $extra));
        }

        return redirect()->back()->with('success', $message);
    }
}
