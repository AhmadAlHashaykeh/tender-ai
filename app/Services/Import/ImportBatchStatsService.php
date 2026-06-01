<?php

namespace App\Services\Import;

use App\Enums\ImportRowValidationStatus;
use App\Enums\StandardizationStatus;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Support\Facades\DB;

class ImportBatchStatsService
{
    /**
     * Single-query validation + standardization aggregates for a batch.
     *
     * @return array{
     *     total: int,
     *     valid: int,
     *     invalid: int,
     *     warning: int,
     *     duplicate: int,
     *     validation_review: int,
     *     standardization_pending: int,
     *     standardization_auto_approved: int,
     *     standardization_review: int,
     *     standardization_skipped: int,
     *     standardization_rejected: int,
     *     materializable: int
     * }
     */
    public function rowCounts(int $batchId, ?ImportBatch $batch = null): array
    {
        $batch = $batch ?? ImportBatch::query()->find($batchId);
        $metadata = $batch?->metadata ?? [];

        if ($batch && $this->canUseCachedValidationCounts($batch)) {
            return [
                'total' => (int) $batch->row_count,
                'valid' => (int) ($metadata['valid_rows'] ?? $batch->success_count),
                'invalid' => (int) ($metadata['invalid_rows'] ?? $batch->error_count),
                'warning' => (int) ($metadata['warning_rows'] ?? 0),
                'duplicate' => (int) ($metadata['duplicate_rows'] ?? $batch->duplicate_count),
                'validation_review' => (int) ($metadata['validation_review_rows'] ?? 0),
                'standardization_pending' => $this->countByStatus($batchId, StandardizationStatus::Pending->value),
                'standardization_auto_approved' => (int) ($metadata['auto_approved_rows'] ?? $this->countByStatus($batchId, StandardizationStatus::AutoApproved->value)),
                'standardization_review' => (int) ($metadata['standardization_review_rows'] ?? $this->countByStatus($batchId, StandardizationStatus::ReviewRequired->value)),
                'standardization_skipped' => (int) ($metadata['standardization_skipped_rows'] ?? $this->countByStatus($batchId, StandardizationStatus::Skipped->value)),
                'standardization_rejected' => (int) ($metadata['standardization_rejected_rows'] ?? $this->countByStatus($batchId, StandardizationStatus::Rejected->value)),
                'materializable' => $this->countMaterializable($batchId),
            ];
        }

        return $this->rowCountsFromAggregateQuery($batchId);
    }

    protected function canUseCachedValidationCounts(ImportBatch $batch): bool
    {
        return in_array($batch->status, [
            'completed',
            'completed_with_errors',
        ], true) && $batch->row_count > 0;
    }

    /**
     * @return array<string, int>
     */
    public function rowCountsFromAggregateQuery(int $batchId): array
    {
        $valid = ImportRowValidationStatus::Valid->value;
        $invalid = ImportRowValidationStatus::Invalid->value;
        $warning = ImportRowValidationStatus::Warning->value;
        $duplicate = ImportRowValidationStatus::Duplicate->value;

        $pending = StandardizationStatus::Pending->value;
        $auto = StandardizationStatus::AutoApproved->value;
        $approved = StandardizationStatus::Approved->value;
        $review = StandardizationStatus::ReviewRequired->value;
        $skipped = StandardizationStatus::Skipped->value;
        $rejected = StandardizationStatus::Rejected->value;

        $row = DB::table('import_rows')
            ->where('import_batch_id', $batchId)
            ->select([
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN validation_status = '{$valid}' THEN 1 ELSE 0 END) as valid_count"),
                DB::raw("SUM(CASE WHEN validation_status = '{$invalid}' THEN 1 ELSE 0 END) as invalid_count"),
                DB::raw("SUM(CASE WHEN validation_status = '{$warning}' THEN 1 ELSE 0 END) as warning_count"),
                DB::raw("SUM(CASE WHEN validation_status = '{$duplicate}' THEN 1 ELSE 0 END) as duplicate_count"),
                DB::raw("SUM(CASE WHEN standardization_status = '{$pending}' THEN 1 ELSE 0 END) as std_pending"),
                DB::raw("SUM(CASE WHEN standardization_status = '{$auto}' THEN 1 ELSE 0 END) as std_auto"),
                DB::raw("SUM(CASE WHEN standardization_status = '{$review}' THEN 1 ELSE 0 END) as std_review"),
                DB::raw("SUM(CASE WHEN standardization_status = '{$skipped}' THEN 1 ELSE 0 END) as std_skipped"),
                DB::raw("SUM(CASE WHEN standardization_status = '{$rejected}' THEN 1 ELSE 0 END) as std_rejected"),
                DB::raw("SUM(CASE WHEN standardization_status IN ('{$auto}', '{$approved}') AND validation_status IN ('{$valid}', '{$warning}') THEN 1 ELSE 0 END) as materializable"),
            ])
            ->first();

        $validCount = (int) ($row->valid_count ?? 0);
        $warningCount = (int) ($row->warning_count ?? 0);
        $duplicateCount = (int) ($row->duplicate_count ?? 0);

        return [
            'total' => (int) ($row->total ?? 0),
            'valid' => $validCount,
            'invalid' => (int) ($row->invalid_count ?? 0),
            'warning' => $warningCount,
            'duplicate' => $duplicateCount,
            'validation_review' => $warningCount + $duplicateCount,
            'standardization_pending' => (int) ($row->std_pending ?? 0),
            'standardization_auto_approved' => (int) ($row->std_auto ?? 0),
            'standardization_review' => (int) ($row->std_review ?? 0),
            'standardization_skipped' => (int) ($row->std_skipped ?? 0),
            'standardization_rejected' => (int) ($row->std_rejected ?? 0),
            'materializable' => (int) ($row->materializable ?? 0),
        ];
    }

    protected function countByStatus(int $batchId, string $status): int
    {
        return ImportRow::query()
            ->where('import_batch_id', $batchId)
            ->where('standardization_status', $status)
            ->count();
    }

    protected function countMaterializable(int $batchId): int
    {
        return ImportRow::query()
            ->where('import_batch_id', $batchId)
            ->whereIn('standardization_status', [
                StandardizationStatus::AutoApproved->value,
                StandardizationStatus::Approved->value,
            ])
            ->whereIn('validation_status', [
                ImportRowValidationStatus::Valid->value,
                ImportRowValidationStatus::Warning->value,
            ])
            ->count();
    }

    /**
     * @return array{
     *     score: float,
     *     rating: string,
     *     breakdown: array<string, float>
     * }
     */
    public function cachedQuality(ImportBatch $batch): array
    {
        $metadata = $batch->metadata ?? [];

        if (isset($metadata['import_quality_score'])) {
            return [
                'score' => (float) $metadata['import_quality_score'],
                'rating' => (string) ($metadata['import_quality_rating'] ?? 'Needs Review'),
                'breakdown' => $metadata['import_quality_breakdown'] ?? [],
            ];
        }

        return [
            'score' => 0.0,
            'rating' => 'Needs Review',
            'breakdown' => [],
        ];
    }
}
