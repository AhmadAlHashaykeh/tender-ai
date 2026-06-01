<?php

namespace App\Services\Import;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportChunkStatus;
use App\Models\ImportBatch;
use App\Models\ImportChunk;

class ImportProgressService
{
    /**
     * @return array{
     *     status: string,
     *     progress: int,
     *     processed_rows: int,
     *     total_rows: int,
     *     completed_chunks: int,
     *     total_chunks: int,
     *     valid_rows: int,
     *     warning_rows: int,
     *     invalid_rows: int,
     *     duplicate_rows: int,
     *     failed_chunks: int,
     *     is_complete: bool,
     *     uses_chunks: bool
     * }
     */
    public function snapshot(ImportBatch $batch): array
    {
        $batch = $batch->fresh();

        if (! $batch->usesChunkedImport()) {
            return $this->legacySnapshot($batch);
        }

        $chunks = ImportChunk::query()
            ->where('import_batch_id', $batch->id)
            ->get();

        $totalChunks = $chunks->count();
        $completedChunks = $chunks->where('status', ImportChunkStatus::Completed->value)->count();
        $failedChunks = $chunks->where('status', ImportChunkStatus::Failed->value)->count();

        $totalRows = (int) $chunks->sum('total_rows');
        $processedRows = (int) $chunks->sum('processed_rows');

        if ($totalRows === 0 && ($batch->metadata['estimated_row_count'] ?? 0) > 0) {
            $totalRows = (int) $batch->metadata['estimated_row_count'];
        }

        if ($totalRows === 0) {
            $totalRows = max(1, (int) $batch->row_count);
        }

        $progress = $totalRows > 0
            ? (int) min(100, round(($processedRows / $totalRows) * 100))
            : 0;

        $metadata = $batch->metadata ?? [];

        return [
            'status' => $batch->status,
            'progress' => $progress,
            'processed_rows' => $processedRows > 0 ? $processedRows : (int) $batch->processed_count,
            'total_rows' => $totalRows > 0 ? $totalRows : (int) $batch->row_count,
            'completed_chunks' => $completedChunks,
            'total_chunks' => $totalChunks,
            'valid_rows' => (int) $chunks->sum('valid_rows') ?: (int) ($metadata['valid_rows'] ?? $batch->success_count),
            'warning_rows' => (int) $chunks->sum('warning_rows') ?: (int) ($metadata['warning_rows'] ?? 0),
            'invalid_rows' => (int) $chunks->sum('invalid_rows') ?: (int) ($metadata['invalid_rows'] ?? $batch->error_count),
            'duplicate_rows' => (int) $chunks->sum('duplicate_rows') ?: (int) ($metadata['duplicate_rows'] ?? $batch->duplicate_count),
            'failed_chunks' => $failedChunks,
            'is_complete' => in_array($batch->status, [
                ImportBatchStatus::Completed->value,
                ImportBatchStatus::CompletedWithErrors->value,
                ImportBatchStatus::Failed->value,
            ], true),
            'uses_chunks' => true,
            'standardization' => $this->standardizationSnapshot($batch, $metadata),
            'materialization' => $this->materializationSnapshot($batch, $metadata),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function materializationSnapshot(ImportBatch $batch, ?array $metadata = null): array
    {
        $metadata = $metadata ?? $batch->metadata ?? [];
        $summary = $metadata['materialization_summary'] ?? [];

        $total = (int) ($metadata['materialization_total_rows'] ?? 0);
        $processed = (int) ($metadata['materialization_processed_rows'] ?? 0);
        $progress = $total > 0 ? (int) min(100, round(($processed / $total) * 100)) : 0;

        return [
            'status' => $metadata['materialization_status'] ?? 'not_started',
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
            'is_complete' => in_array($metadata['materialization_status'] ?? '', ['completed', 'failed'], true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function standardizationSnapshot(ImportBatch $batch, ?array $metadata = null): array
    {
        $metadata = $metadata ?? $batch->metadata ?? [];
        $summary = $metadata['standardization_summary'] ?? [];

        $total = (int) ($metadata['standardization_total_rows'] ?? 0);
        $processed = (int) ($metadata['standardization_processed_rows'] ?? 0);
        $progress = $total > 0 ? (int) min(100, round(($processed / $total) * 100)) : 0;

        return [
            'status' => $metadata['standardization_status'] ?? 'not_started',
            'progress' => $progress,
            'processed_rows' => $processed,
            'total_rows' => $total,
            'completed_chunks' => (int) ($metadata['standardization_completed_chunks'] ?? 0),
            'total_chunks' => (int) ($metadata['standardization_total_chunks'] ?? 0),
            'failed_chunks' => (int) ($metadata['standardization_failed_chunks'] ?? 0),
            'failed_rows' => (int) ($metadata['standardization_failed_rows'] ?? 0),
            'auto_approved' => (int) ($summary['auto_approved'] ?? $metadata['auto_approved_rows'] ?? 0),
            'review_required' => (int) ($summary['review_required'] ?? $metadata['standardization_review_rows'] ?? 0),
            'rejected' => (int) ($summary['rejected'] ?? $metadata['standardization_rejected_rows'] ?? 0),
            'is_complete' => in_array($metadata['standardization_status'] ?? '', ['completed', 'failed'], true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function legacySnapshot(ImportBatch $batch): array
    {
        $metadata = $batch->metadata ?? [];
        $totalRows = max(1, (int) $batch->row_count);
        $processedRows = (int) $batch->processed_count;

        $isComplete = in_array($batch->status, [
            ImportBatchStatus::Completed->value,
            ImportBatchStatus::CompletedWithErrors->value,
            ImportBatchStatus::Failed->value,
        ], true);

        $progress = $isComplete
            ? 100
            : (int) min(100, round(($processedRows / $totalRows) * 100));

        return [
            'status' => $batch->status,
            'progress' => $progress,
            'processed_rows' => $processedRows,
            'total_rows' => (int) $batch->row_count,
            'completed_chunks' => $isComplete ? 1 : 0,
            'total_chunks' => $isComplete ? 1 : 0,
            'valid_rows' => (int) ($metadata['valid_rows'] ?? $batch->success_count),
            'warning_rows' => (int) ($metadata['warning_rows'] ?? 0),
            'invalid_rows' => (int) ($metadata['invalid_rows'] ?? $batch->error_count),
            'duplicate_rows' => (int) ($metadata['duplicate_rows'] ?? $batch->duplicate_count),
            'failed_chunks' => $batch->status === ImportBatchStatus::Failed->value ? 1 : 0,
            'is_complete' => $isComplete,
            'uses_chunks' => false,
            'standardization' => $this->standardizationSnapshot($batch, $metadata),
            'materialization' => $this->materializationSnapshot($batch, $metadata),
        ];
    }
}
