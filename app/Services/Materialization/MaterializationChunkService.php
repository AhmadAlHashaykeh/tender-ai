<?php

namespace App\Services\Materialization;

use App\Enums\ImportRowValidationStatus;
use App\Enums\MaterializationChunkStatus;
use App\Enums\StandardizationStatus;
use App\Jobs\MaterializeImportBatchJob;
use App\Jobs\MaterializeImportChunkJob;
use App\Services\Import\ImportChunkSizeResolver;
use App\Services\Import\ImportJobDispatcher;
use App\Services\Import\ImportPipelineOrchestratorService;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\MaterializationChunk;
use Illuminate\Support\Collection;
use Throwable;

class MaterializationChunkService
{
    public function __construct(
        protected ImportMaterializationService $materializationService,
        protected MaterializationLookupCache $lookupCache,
    ) {}

    public function dispatchBatchJob(ImportBatch $batch): void
    {
        $eligibleCount = $this->countEligibleRows($batch);

        if ($eligibleCount === 0) {
            return;
        }

        $batch = $batch->fresh();
        $metadata = $batch->metadata ?? [];
        $status = $metadata['materialization_status'] ?? 'not_started';

        if (in_array($status, ['completed', 'failed'], true)) {
            return;
        }

        if (in_array($status, ['preparing', 'processing'], true)) {
            app(ImportJobDispatcher::class)->dispatch(new MaterializeImportBatchJob($batch->id), $batch);

            return;
        }

        $batch->update([
            'metadata' => array_merge($metadata, [
                'materialization_status' => 'preparing',
                'materialization_started_at' => now()->toIso8601String(),
                'materialization_completed_at' => null,
                'materialization_last_error' => null,
                'materialization_total_rows' => $eligibleCount,
                'materialization_processed_rows' => 0,
                'materialization_materialized_rows' => 0,
                'materialization_skipped_rows' => 0,
                'materialization_failed_rows' => 0,
                'materialization_summary' => null,
            ]),
        ]);

        app(ImportJobDispatcher::class)->dispatch(new MaterializeImportBatchJob($batch->id), $batch);
    }

    /**
     * Create chunks when needed, recover stuck work, dispatch pending chunks, finalize when done.
     */
    public function resumeOrOrchestrate(ImportBatch $batch): void
    {
        $batch = $batch->fresh();
        $metadata = $batch->metadata ?? [];
        $status = $metadata['materialization_status'] ?? 'not_started';

        if (in_array($status, ['completed', 'failed'], true)) {
            return;
        }

        $rowNumbers = $this->eligibleRowNumbers($batch);

        if ($rowNumbers === []) {
            $this->finalizeBatch($batch, $this->emptySummary());

            return;
        }

        if (! $batch->usesChunkedMaterialization()) {
            $this->createChunksForBatch($batch, $rowNumbers);
            $batch = $batch->fresh();
        }

        $this->recoverStuckChunks($batch);

        $chunks = MaterializationChunk::query()
            ->where('import_batch_id', $batch->id)
            ->orderBy('chunk_number')
            ->get();

        $hasPending = $chunks->contains(
            fn (MaterializationChunk $chunk) => $chunk->status === MaterializationChunkStatus::Pending->value,
        );

        $hasProcessing = $chunks->contains(
            fn (MaterializationChunk $chunk) => $chunk->status === MaterializationChunkStatus::Processing->value,
        );

        if ($hasPending) {
            $this->dispatchChunkJobs($batch->fresh());
            $this->markMaterializationProcessing($batch->fresh(), $rowNumbers, $chunks);
        } elseif ($hasProcessing) {
            $this->markMaterializationProcessing($batch->fresh(), $rowNumbers, $chunks);
        } else {
            $this->checkBatchFinalization($batch->fresh());
        }
    }

    /** @deprecated Use resumeOrOrchestrate — kept for backward compatibility in tests. */
    public function orchestrate(ImportBatch $batch): void
    {
        $this->resumeOrOrchestrate($batch);
    }

    /**
     * Reset chunks stuck in processing longer than the configured timeout.
     */
    public function recoverStuckChunks(ImportBatch $batch): int
    {
        $timeoutMinutes = max(1, (int) config('import.materialization_stuck_chunk_minutes', 10));
        $cutoff = now()->subMinutes($timeoutMinutes);

        $stuck = MaterializationChunk::query()
            ->where('import_batch_id', $batch->id)
            ->where('status', MaterializationChunkStatus::Processing->value)
            ->where('updated_at', '<', $cutoff)
            ->get();

        foreach ($stuck as $chunk) {
            $chunk->update([
                'status' => MaterializationChunkStatus::Pending->value,
                'retry_count' => (int) $chunk->retry_count + 1,
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null,
            ]);
        }

        return $stuck->count();
    }

    /**
     * @param  list<int>  $rowNumbers
     * @return list<MaterializationChunk>
     */
    public function createChunksForBatch(ImportBatch $batch, array $rowNumbers): array
    {
        $chunkSize = ImportChunkSizeResolver::forRowCount(
            count($rowNumbers),
            'import.materialization_chunk_size',
            100,
        );
        $chunks = [];
        $chunkIndex = 0;

        foreach (array_chunk($rowNumbers, $chunkSize) as $group) {
            $chunkIndex++;
            $chunks[] = MaterializationChunk::query()->create([
                'import_batch_id' => $batch->id,
                'chunk_number' => $chunkIndex,
                'start_row_number' => min($group),
                'end_row_number' => max($group),
                'status' => MaterializationChunkStatus::Pending->value,
                'total_rows' => count($group),
            ]);
        }

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'materialization_total_chunks' => count($chunks),
                'materialization_chunk_size' => $chunkSize,
            ]),
        ]);

        return $chunks;
    }

    public function dispatchChunkJobs(ImportBatch $batch): int
    {
        $dispatched = 0;

        MaterializationChunk::query()
            ->where('import_batch_id', $batch->id)
            ->where('status', MaterializationChunkStatus::Pending->value)
            ->orderBy('chunk_number')
            ->each(function (MaterializationChunk $chunk) use ($batch, &$dispatched): void {
                app(ImportJobDispatcher::class)->dispatch(new MaterializeImportChunkJob($chunk), $batch);
                $dispatched++;
            });

        return $dispatched;
    }

    public function processChunk(MaterializationChunk $chunk): void
    {
        $batch = $chunk->importBatch()->first();

        if (! $batch || $chunk->status === MaterializationChunkStatus::Completed->value) {
            return;
        }

        $chunk->update([
            'status' => MaterializationChunkStatus::Processing->value,
            'started_at' => $chunk->started_at ?? now(),
            'error_message' => null,
        ]);

        $this->markMaterializationProcessing($batch->fresh());

        $summary = $this->emptySummary();

        try {
            $this->lookupCache->warmup();

            $rowsQuery = ImportRow::query()
                ->where('import_batch_id', $batch->id)
                ->whereBetween('row_number', [$chunk->start_row_number, $chunk->end_row_number])
                ->whereIn('validation_status', [
                    ImportRowValidationStatus::Valid->value,
                    ImportRowValidationStatus::Warning->value,
                ])
                ->whereIn('standardization_status', [
                    StandardizationStatus::AutoApproved->value,
                    StandardizationStatus::Approved->value,
                ])
                ->orderBy('row_number');

            $rowIds = (clone $rowsQuery)->pluck('id');
            $this->lookupCache->seedMaterializedRowIds($rowIds);

            $countryIds = (clone $rowsQuery)
                ->get()
                ->map(fn (ImportRow $row) => $this->materializationService->resolveCountryId($row, false))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $this->lookupCache->preloadTendersForCountries($countryIds);

            $rowsQuery->chunkById(50, function (Collection $rows) use (&$summary): void {
                foreach ($rows as $row) {
                    $outcome = $this->materializationService->materializeRow($row, true, $this->lookupCache);
                    $summary['processed']++;
                    $summary[$outcome['bucket']]++;
                    $summary['companies_created'] += $outcome['companies_created'];
                    $summary['drugs_created'] += $outcome['drugs_created'];
                    $summary['tenders_created'] += $outcome['tenders_created'];
                    $summary['tender_items_created'] += $outcome['tender_items_created'];
                    $summary['bid_records_created'] += $outcome['bid_records_created'];
                }
            });
        } catch (Throwable $exception) {
            $this->failChunk($chunk, $exception->getMessage());
            $this->syncBatchProgress($batch->fresh());
            $this->checkBatchFinalization($batch->fresh());

            return;
        } finally {
            $this->lookupCache->clear();
        }

        $chunk->update([
            'status' => MaterializationChunkStatus::Completed->value,
            'processed_rows' => $summary['processed'],
            'materialized_rows' => $summary['materialized'],
            'skipped_rows' => $summary['skipped'],
            'failed_rows' => $summary['failed'],
            'completed_at' => now(),
        ]);

        $this->syncBatchProgress($batch->fresh());
        $this->checkBatchFinalization($batch->fresh());
    }

    public function retryFailedChunks(ImportBatch $batch): int
    {
        $failed = MaterializationChunk::query()
            ->where('import_batch_id', $batch->id)
            ->where('status', MaterializationChunkStatus::Failed->value)
            ->get();

        if ($failed->isEmpty()) {
            return 0;
        }

        foreach ($failed as $chunk) {
            $chunk->update([
                'status' => MaterializationChunkStatus::Pending->value,
                'processed_rows' => 0,
                'materialized_rows' => 0,
                'skipped_rows' => 0,
                'failed_rows' => 0,
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null,
            ]);

            app(ImportJobDispatcher::class)->dispatch(new MaterializeImportChunkJob($chunk), $batch);
        }

        $this->markMaterializationProcessing($batch->fresh());

        return $failed->count();
    }

    public function checkBatchFinalization(ImportBatch $batch): void
    {
        if (! $batch->usesChunkedMaterialization()) {
            return;
        }

        $pending = MaterializationChunk::query()
            ->where('import_batch_id', $batch->id)
            ->whereIn('status', [
                MaterializationChunkStatus::Pending->value,
                MaterializationChunkStatus::Processing->value,
            ])
            ->exists();

        if ($pending) {
            return;
        }

        $chunks = MaterializationChunk::query()->where('import_batch_id', $batch->id)->get();
        $failedChunks = $chunks->where('status', MaterializationChunkStatus::Failed->value)->count();

        $summary = [
            'processed' => (int) $chunks->sum('processed_rows'),
            'materialized' => (int) $chunks->sum('materialized_rows'),
            'skipped' => (int) $chunks->sum('skipped_rows'),
            'failed' => (int) $chunks->sum('failed_rows'),
        ];

        $status = $failedChunks > 0 && $chunks->where('status', MaterializationChunkStatus::Completed->value)->isEmpty()
            ? 'failed'
            : 'completed';

        $this->finalizeBatch($batch, $summary, $status, $failedChunks);
    }

    public function syncBatchProgress(ImportBatch $batch): void
    {
        $chunks = MaterializationChunk::query()->where('import_batch_id', $batch->id)->get();

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'materialization_processed_rows' => (int) $chunks->sum('processed_rows'),
                'materialization_materialized_rows' => (int) $chunks->sum('materialized_rows'),
                'materialization_skipped_rows' => (int) $chunks->sum('skipped_rows'),
                'materialization_failed_rows' => (int) $chunks->sum('failed_rows'),
                'materialization_completed_chunks' => $chunks->where('status', MaterializationChunkStatus::Completed->value)->count(),
                'materialization_failed_chunks' => $chunks->where('status', MaterializationChunkStatus::Failed->value)->count(),
                'materialization_summary' => [
                    'processed' => (int) $chunks->sum('processed_rows'),
                    'materialized' => (int) $chunks->sum('materialized_rows'),
                    'skipped' => (int) $chunks->sum('skipped_rows'),
                    'failed' => (int) $chunks->sum('failed_rows'),
                ],
            ]),
        ]);
    }

    public function materializationProgress(ImportBatch $batch): array
    {
        $metadata = $batch->metadata ?? [];
        $summary = $metadata['materialization_summary'] ?? [];

        $total = (int) ($metadata['materialization_total_rows'] ?? 0);
        $processed = (int) ($metadata['materialization_processed_rows'] ?? 0);
        $progress = $total > 0 ? (int) min(100, round(($processed / $total) * 100)) : 0;

        $status = $metadata['materialization_status'] ?? 'not_started';

        return [
            'status' => $status,
            'progress' => $progress,
            'processed_rows' => $processed,
            'total_rows' => $total,
            'materialized_rows' => (int) ($metadata['materialization_materialized_rows'] ?? $summary['materialized'] ?? 0),
            'skipped_rows' => (int) ($metadata['materialization_skipped_rows'] ?? $summary['skipped'] ?? 0),
            'failed_rows' => (int) ($metadata['materialization_failed_rows'] ?? $summary['failed'] ?? 0),
            'completed_chunks' => (int) ($metadata['materialization_completed_chunks'] ?? 0),
            'total_chunks' => (int) ($metadata['materialization_total_chunks'] ?? 0),
            'failed_chunks' => (int) ($metadata['materialization_failed_chunks'] ?? 0),
            'last_error' => $metadata['materialization_last_error'] ?? null,
            'summary' => $summary,
            'started_at' => $metadata['materialization_started_at'] ?? null,
            'completed_at' => $metadata['materialization_completed_at'] ?? null,
            'is_complete' => in_array($status, ['completed', 'failed'], true),
            'is_active' => in_array($status, ['preparing', 'processing'], true),
        ];
    }

    /**
     * @param  list<int>|null  $rowNumbers
     * @param  \Illuminate\Support\Collection<int, MaterializationChunk>|null  $chunks
     */
    protected function markMaterializationProcessing(
        ImportBatch $batch,
        ?array $rowNumbers = null,
        ?Collection $chunks = null,
    ): void {
        $metadata = $batch->metadata ?? [];
        $rowNumbers ??= $this->eligibleRowNumbers($batch);
        $chunks ??= MaterializationChunk::query()
            ->where('import_batch_id', $batch->id)
            ->get();

        $batch->update([
            'metadata' => array_merge($metadata, [
                'materialization_status' => 'processing',
                'materialization_total_rows' => count($rowNumbers) > 0
                    ? count($rowNumbers)
                    : (int) ($metadata['materialization_total_rows'] ?? 0),
                'materialization_total_chunks' => $chunks->count() > 0
                    ? $chunks->count()
                    : (int) ($metadata['materialization_total_chunks'] ?? 0),
                'materialization_chunk_size' => (int) ($metadata['materialization_chunk_size']
                    ?? max(1, (int) config('import.materialization_chunk_size', 100))),
            ]),
        ]);
    }

    protected function countEligibleRows(ImportBatch $batch): int
    {
        return count($this->eligibleRowNumbers($batch));
    }

    /**
     * @return list<int>
     */
    protected function eligibleRowNumbers(ImportBatch $batch): array
    {
        return ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->whereIn('validation_status', [
                ImportRowValidationStatus::Valid->value,
                ImportRowValidationStatus::Warning->value,
            ])
            ->whereIn('standardization_status', [
                StandardizationStatus::AutoApproved->value,
                StandardizationStatus::Approved->value,
            ])
            ->whereNull('bid_record_id')
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('bid_records')
                    ->whereColumn('bid_records.source_import_row_id', 'import_rows.id');
            })
            ->orderBy('row_number')
            ->pluck('row_number')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * @param  array{processed: int, materialized: int, skipped: int, failed: int}  $summary
     */
    protected function finalizeBatch(
        ImportBatch $batch,
        array $summary,
        string $status = 'completed',
        int $failedChunks = 0,
    ): void {
        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'materialization_status' => $status,
                'materialization_completed_at' => now()->toIso8601String(),
                'materialization_processed_rows' => $summary['processed'],
                'materialization_materialized_rows' => $summary['materialized'],
                'materialization_skipped_rows' => $summary['skipped'],
                'materialization_failed_rows' => $summary['failed'],
                'materialization_failed_chunks' => $failedChunks,
                'materialization_last_error' => $summary['failed'] > 0
                    ? sprintf('%d row(s) failed during materialization.', $summary['failed'])
                    : ($failedChunks > 0 ? 'One or more materialization chunks failed.' : null),
                'materialization_summary' => $summary,
            ]),
        ]);

        $this->materializationService->updateBatchCounts($batch->fresh());

        if ($status === 'completed') {
            app(ImportPipelineOrchestratorService::class)->onMaterializationComplete($batch->fresh());
        }
    }

    protected function failChunk(MaterializationChunk $chunk, string $message): void
    {
        $chunk->update([
            'status' => MaterializationChunkStatus::Failed->value,
            'error_message' => $message,
            'completed_at' => now(),
        ]);
    }

    /**
     * @return array{processed: int, materialized: int, skipped: int, failed: int, companies_created: int, drugs_created: int, tenders_created: int, tender_items_created: int, bid_records_created: int}
     */
    protected function emptySummary(): array
    {
        return [
            'processed' => 0,
            'materialized' => 0,
            'skipped' => 0,
            'failed' => 0,
            'companies_created' => 0,
            'drugs_created' => 0,
            'tenders_created' => 0,
            'tender_items_created' => 0,
            'bid_records_created' => 0,
        ];
    }
}
