<?php

namespace App\Services\Standardization;

use App\Enums\StandardizationChunkStatus;
use App\Enums\StandardizationStatus;
use App\Jobs\StandardizeImportChunkJob;
use App\Services\Import\ImportChunkSizeResolver;
use App\Services\Import\ImportJobDispatcher;
use App\Services\Import\ImportPipelineOrchestratorService;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\StandardizationChunk;
use Illuminate\Support\Collection;
use Throwable;

class StandardizationChunkService
{
    public function __construct(
        protected ImportRowStandardizationService $standardizationService,
        protected EntityMatchIndexService $matchIndex,
    ) {}

    /**
     * @return list<StandardizationChunk>
     */
    public function createChunksForBatch(ImportBatch $batch): array
    {
        $rowNumbers = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->where('standardization_status', StandardizationStatus::Pending->value)
            ->orderBy('row_number')
            ->pluck('row_number')
            ->map(fn ($n) => (int) $n)
            ->all();

        if ($rowNumbers === []) {
            return [];
        }

        $chunkSize = ImportChunkSizeResolver::forRowCount(
            count($rowNumbers),
            'import.standardization_chunk_size',
            100,
        );
        $chunks = [];
        $chunkIndex = 0;

        foreach (array_chunk($rowNumbers, $chunkSize) as $group) {
            $chunkIndex++;
            $chunks[] = StandardizationChunk::query()->create([
                'import_batch_id' => $batch->id,
                'chunk_number' => $chunkIndex,
                'start_row_number' => min($group),
                'end_row_number' => max($group),
                'status' => StandardizationChunkStatus::Pending->value,
                'total_rows' => count($group),
            ]);
        }

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'standardization_total_chunks' => count($chunks),
                'standardization_chunk_size' => $chunkSize,
            ]),
        ]);

        return $chunks;
    }

    public function dispatchChunkJobs(ImportBatch $batch): void
    {
        StandardizationChunk::query()
            ->where('import_batch_id', $batch->id)
            ->where('status', StandardizationChunkStatus::Pending->value)
            ->orderBy('chunk_number')
            ->each(function (StandardizationChunk $chunk) use ($batch): void {
                app(ImportJobDispatcher::class)->dispatch(new StandardizeImportChunkJob($chunk), $batch);
            });
    }

    public function orchestrate(ImportBatch $batch): void
    {
        $batch = $batch->fresh();
        $pendingCount = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->where('standardization_status', StandardizationStatus::Pending->value)
            ->count();

        if ($pendingCount === 0) {
            $this->finalizeBatch($batch, [
                'processed' => 0,
                'auto_approved' => 0,
                'review_required' => 0,
                'skipped' => 0,
                'rejected' => 0,
                'failed' => 0,
            ]);

            return;
        }

        $chunks = $this->createChunksForBatch($batch);

        if ($chunks === []) {
            $this->finalizeBatch($batch, [
                'processed' => 0,
                'auto_approved' => 0,
                'review_required' => 0,
                'skipped' => 0,
                'rejected' => 0,
                'failed' => 0,
            ]);

            return;
        }

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'standardization_status' => 'processing',
                'standardization_total_rows' => $pendingCount,
                'standardization_processed_rows' => (int) ($batch->metadata['standardization_processed_rows'] ?? 0),
            ]),
        ]);

        $this->dispatchChunkJobs($batch->fresh());
    }

    public function processChunk(StandardizationChunk $chunk): void
    {
        $batch = $chunk->importBatch()->first();

        if (! $batch || $chunk->status === StandardizationChunkStatus::Completed->value) {
            return;
        }

        $chunk->update([
            'status' => StandardizationChunkStatus::Processing->value,
            'started_at' => $chunk->started_at ?? now(),
            'error_message' => null,
        ]);

        $summary = [
            'processed' => 0,
            'auto_approved' => 0,
            'review_required' => 0,
            'skipped' => 0,
            'rejected' => 0,
            'failed' => 0,
        ];

        $this->matchIndex->warmupCaches();

        try {
            ImportRow::query()
                ->where('import_batch_id', $batch->id)
                ->whereBetween('row_number', [$chunk->start_row_number, $chunk->end_row_number])
                ->where('standardization_status', StandardizationStatus::Pending->value)
                ->orderBy('row_number')
                ->chunkById(50, function (Collection $rows) use (&$summary): void {
                    foreach ($rows as $row) {
                        $result = $this->standardizationService->standardizeRowSafely($row);
                        $summary['processed']++;
                        $summary[$result['status_bucket']]++;
                        if ($result['failed']) {
                            $summary['failed']++;
                        }
                    }
                });
        } catch (Throwable $exception) {
            $this->failChunk($chunk, $exception->getMessage());
            $this->syncBatchProgress($batch->fresh());
            $this->checkBatchFinalization($batch->fresh());

            return;
        } finally {
            $this->matchIndex->clearCache();
        }

        $rowIds = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->whereBetween('row_number', [$chunk->start_row_number, $chunk->end_row_number])
            ->pluck('id');

        $auto = ImportRow::query()->whereIn('id', $rowIds)
            ->where('standardization_status', StandardizationStatus::AutoApproved->value)->count();
        $review = ImportRow::query()->whereIn('id', $rowIds)
            ->where('standardization_status', StandardizationStatus::ReviewRequired->value)->count();
        $skipped = ImportRow::query()->whereIn('id', $rowIds)
            ->where('standardization_status', StandardizationStatus::Skipped->value)->count();
        $rejected = ImportRow::query()->whereIn('id', $rowIds)
            ->where('standardization_status', StandardizationStatus::Rejected->value)->count();

        $chunk->update([
            'status' => StandardizationChunkStatus::Completed->value,
            'processed_rows' => $summary['processed'],
            'auto_approved_rows' => $auto,
            'review_required_rows' => $review,
            'skipped_rows' => $skipped,
            'rejected_rows' => $rejected,
            'failed_rows' => $summary['failed'],
            'completed_at' => now(),
        ]);

        $this->syncBatchProgress($batch->fresh());
        $this->checkBatchFinalization($batch->fresh());
    }

    public function retryFailedChunks(ImportBatch $batch): int
    {
        $failed = StandardizationChunk::query()
            ->where('import_batch_id', $batch->id)
            ->where('status', StandardizationChunkStatus::Failed->value)
            ->get();

        if ($failed->isEmpty()) {
            return 0;
        }

        foreach ($failed as $chunk) {
            $chunk->update([
                'status' => StandardizationChunkStatus::Pending->value,
                'processed_rows' => 0,
                'auto_approved_rows' => 0,
                'review_required_rows' => 0,
                'skipped_rows' => 0,
                'rejected_rows' => 0,
                'failed_rows' => 0,
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null,
            ]);

            StandardizeImportChunkJob::dispatch($chunk);
        }

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'standardization_status' => 'processing',
                'standardization_completed_at' => null,
            ]),
        ]);

        return $failed->count();
    }

    public function checkBatchFinalization(ImportBatch $batch): void
    {
        if (! $batch->usesChunkedStandardization()) {
            return;
        }

        $pending = StandardizationChunk::query()
            ->where('import_batch_id', $batch->id)
            ->whereIn('status', [
                StandardizationChunkStatus::Pending->value,
                StandardizationChunkStatus::Processing->value,
            ])
            ->exists();

        if ($pending) {
            return;
        }

        $chunks = StandardizationChunk::query()->where('import_batch_id', $batch->id)->get();
        $failedChunks = $chunks->where('status', StandardizationChunkStatus::Failed->value)->count();

        $summary = [
            'processed' => (int) $chunks->sum('processed_rows'),
            'auto_approved' => (int) $chunks->sum('auto_approved_rows'),
            'review_required' => (int) $chunks->sum('review_required_rows'),
            'skipped' => (int) $chunks->sum('skipped_rows'),
            'rejected' => (int) $chunks->sum('rejected_rows'),
            'failed' => (int) $chunks->sum('failed_rows'),
        ];

        $status = $failedChunks > 0 && $chunks->where('status', StandardizationChunkStatus::Completed->value)->isEmpty()
            ? 'failed'
            : 'completed';

        $this->finalizeBatch($batch, $summary, $status, $failedChunks);
    }

    /**
     * @param  array{processed: int, auto_approved: int, review_required: int, skipped: int, rejected: int, failed: int}  $summary
     */
    protected function finalizeBatch(
        ImportBatch $batch,
        array $summary,
        string $status = 'completed',
        int $failedChunks = 0,
    ): void {
        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'standardization_status' => $status,
                'standardization_completed_at' => now()->toIso8601String(),
                'standardization_processed_rows' => $summary['processed'],
                'standardization_failed_rows' => $summary['failed'],
                'standardization_failed_chunks' => $failedChunks,
                'standardization_last_error' => $summary['failed'] > 0
                    ? sprintf('%d row(s) failed during standardization.', $summary['failed'])
                    : ($failedChunks > 0 ? 'One or more standardization chunks failed.' : null),
                'standardization_summary' => $summary,
            ]),
        ]);

        $this->standardizationService->updateBatchCounts($batch->fresh());

        if ($status === 'completed') {
            app(ImportPipelineOrchestratorService::class)->onStandardizationComplete($batch->fresh());
        }
    }

    protected function failChunk(StandardizationChunk $chunk, string $message): void
    {
        $chunk->update([
            'status' => StandardizationChunkStatus::Failed->value,
            'error_message' => $message,
            'completed_at' => now(),
        ]);
    }

    public function syncBatchProgress(ImportBatch $batch): void
    {
        $chunks = StandardizationChunk::query()->where('import_batch_id', $batch->id)->get();

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'standardization_processed_rows' => (int) $chunks->sum('processed_rows'),
                'standardization_failed_rows' => (int) $chunks->sum('failed_rows'),
                'standardization_completed_chunks' => $chunks->where('status', StandardizationChunkStatus::Completed->value)->count(),
                'standardization_failed_chunks' => $chunks->where('status', StandardizationChunkStatus::Failed->value)->count(),
                'standardization_summary' => [
                    'processed' => (int) $chunks->sum('processed_rows'),
                    'auto_approved' => (int) $chunks->sum('auto_approved_rows'),
                    'review_required' => (int) $chunks->sum('review_required_rows'),
                    'skipped' => (int) $chunks->sum('skipped_rows'),
                    'rejected' => (int) $chunks->sum('rejected_rows'),
                    'failed' => (int) $chunks->sum('failed_rows'),
                ],
            ]),
        ]);
    }
}
