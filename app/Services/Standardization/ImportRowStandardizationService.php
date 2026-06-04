<?php

namespace App\Services\Standardization;

use App\Enums\ImportRowValidationStatus;
use App\Enums\StandardizationStatus;
use App\Jobs\StandardizeImportBatchJob;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\StandardizationLog;
use App\Models\Company;
use App\Models\StandardizedDrug;
use App\Services\Import\ImportBatchService;
use App\Services\Import\ImportJobDispatcher;
use App\Services\Import\ImportPipelineOrchestratorService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Collection;
use Throwable;

class ImportRowStandardizationService
{
    public const DRUG_AUTO_MIN = 85.0;

    public const COMPANY_AUTO_MIN = 85.0;

    public const TENDER_AUTO_MIN = 75.0;

    public const COUNTRY_AUTO_MIN = 80.0;

    public function __construct(
        protected CountryStandardizationService $countryService,
        protected CompanyStandardizationService $companyService,
        protected DrugStandardizationService $drugService,
        protected TenderStandardizationService $tenderService,
        protected SettingsService $settings,
        protected EntityMatchIndexService $matchIndex,
        protected ImportBatchService $importBatchService,
    ) {}

    protected function drugAutoMin(): float
    {
        return (float) ($this->settings->getInteger('standardization.drug_auto_approve_min', (int) self::DRUG_AUTO_MIN) ?? self::DRUG_AUTO_MIN);
    }

    protected function companyAutoMin(): float
    {
        return (float) ($this->settings->getInteger('standardization.company_auto_approve_min', (int) self::COMPANY_AUTO_MIN) ?? self::COMPANY_AUTO_MIN);
    }

    protected function tenderAutoMin(): float
    {
        return (float) ($this->settings->getInteger('standardization.row_auto_approve_min', (int) self::TENDER_AUTO_MIN) ?? self::TENDER_AUTO_MIN);
    }

    protected function countryAutoMin(): float
    {
        return self::COUNTRY_AUTO_MIN;
    }

    /**
     * Queue background standardization for a batch (web UI entry point).
     */
    public function dispatchBatchJob(ImportBatch $batch): void
    {
        $pendingCount = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->where('standardization_status', StandardizationStatus::Pending->value)
            ->count();

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'standardization_status' => 'processing',
                'standardization_started_at' => now()->toIso8601String(),
                'standardization_completed_at' => null,
                'standardization_processed_rows' => 0,
                'standardization_total_rows' => $pendingCount,
                'standardization_failed_rows' => 0,
                'standardization_last_error' => null,
                'standardization_summary' => null,
            ]),
        ]);

        app(ImportJobDispatcher::class)->dispatch(new StandardizeImportBatchJob($batch->id), $batch);
    }

    /**
     * Process a batch with progress metadata — used by queue job and CLI.
     *
     * @return array{
     *     processed: int,
     *     auto_approved: int,
     *     review_required: int,
     *     skipped: int,
     *     rejected: int,
     *     failed: int
     * }
     */
    public function standardizeBatchWithProgress(
        ImportBatch $batch,
        bool $onlyPending = true,
        ?int $limit = null,
    ): array {
        $batch = $batch->fresh();

        $query = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->orderBy('row_number');

        if ($onlyPending) {
            $query->where('standardization_status', StandardizationStatus::Pending->value);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $totalRows = (clone $query)->count();

        $status = $batch->metadata['standardization_status'] ?? 'not_started';

        if ($status !== 'processing') {
            $batch->update([
                'metadata' => array_merge($batch->metadata ?? [], [
                    'standardization_status' => 'processing',
                    'standardization_started_at' => $status === 'not_started'
                        ? now()->toIso8601String()
                        : ($batch->metadata['standardization_started_at'] ?? now()->toIso8601String()),
                    'standardization_completed_at' => null,
                    'standardization_processed_rows' => $status === 'failed'
                        ? (int) ($batch->metadata['standardization_processed_rows'] ?? 0)
                        : 0,
                    'standardization_total_rows' => $status === 'failed'
                        ? (int) ($batch->metadata['standardization_total_rows'] ?? $totalRows)
                        : $totalRows,
                    'standardization_failed_rows' => (int) ($batch->metadata['standardization_failed_rows'] ?? 0),
                    'standardization_last_error' => null,
                ]),
            ]);
        } elseif ((int) ($batch->metadata['standardization_total_rows'] ?? 0) === 0) {
            $this->updateStandardizationProgress($batch, processedDelta: 0, failedDelta: 0, totalRows: $totalRows);
        }

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
            $query->chunkById(100, function (Collection $rows) use ($batch, &$summary): void {
                $chunkFailed = 0;

                foreach ($rows as $row) {
                    if ($row->standardization_status !== StandardizationStatus::Pending->value) {
                        continue;
                    }

                    $result = $this->standardizeRowSafely($row);
                    $summary['processed']++;
                    $summary[$result['status_bucket']]++;

                    if ($result['failed']) {
                        $summary['failed']++;
                        $chunkFailed++;
                    }
                }

                $this->updateStandardizationProgress(
                    $batch,
                    processedDelta: $rows->count(),
                    failedDelta: $chunkFailed,
                );
            });
        } catch (Throwable $exception) {
            $batch->update([
                'metadata' => array_merge($batch->fresh()->metadata ?? [], [
                    'standardization_status' => 'failed',
                    'standardization_completed_at' => now()->toIso8601String(),
                    'standardization_last_error' => $exception->getMessage(),
                ]),
            ]);

            throw $exception;
        } finally {
            $this->matchIndex->clearCache();
        }

        $batch = $batch->fresh();
        $processedRows = (int) ($batch->metadata['standardization_processed_rows'] ?? $summary['processed']);

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'standardization_status' => 'completed',
                'standardization_completed_at' => now()->toIso8601String(),
                'standardization_processed_rows' => $processedRows,
                'standardization_failed_rows' => $summary['failed'],
                'standardization_last_error' => $summary['failed'] > 0
                    ? sprintf('%d row(s) failed during standardization.', $summary['failed'])
                    : null,
                'standardization_summary' => [
                    'processed' => $summary['processed'],
                    'auto_approved' => $summary['auto_approved'],
                    'review_required' => $summary['review_required'],
                    'skipped' => $summary['skipped'],
                    'rejected' => $summary['rejected'],
                    'failed' => $summary['failed'],
                ],
            ]),
        ]);

        $this->updateBatchCounts($batch->fresh());

        return $summary;
    }

    /**
     * @return array{status_bucket: string, failed: bool}
     */
    public function standardizeRowSafely(ImportRow $row): array
    {
        if ($row->standardization_status !== StandardizationStatus::Pending->value) {
            return ['status_bucket' => 'skipped', 'failed' => false];
        }

        try {
            $result = $this->standardizeRow($row, persist: true);

            return [
                'status_bucket' => $result['status_bucket'],
                'failed' => false,
            ];
        } catch (Throwable $exception) {
            $normalized = $row->normalized_data ?? [];
            $normalized['standardization_error'] = $exception->getMessage();

            $row->update([
                'standardization_status' => StandardizationStatus::Rejected->value,
                'error_message' => $row->error_message
                    ? $row->error_message.' | Standardization: '.$exception->getMessage()
                    : 'Standardization: '.$exception->getMessage(),
                'normalized_data' => $normalized,
            ]);

            return [
                'status_bucket' => 'rejected',
                'failed' => true,
            ];
        }
    }

    protected function updateStandardizationProgress(
        ImportBatch $batch,
        int $processedDelta,
        int $failedDelta,
        ?int $totalRows = null,
    ): void {
        $batch = $batch->fresh();
        $metadata = $batch->metadata ?? [];

        $batch->update([
            'metadata' => array_merge($metadata, [
                'standardization_processed_rows' => ((int) ($metadata['standardization_processed_rows'] ?? 0)) + $processedDelta,
                'standardization_failed_rows' => ((int) ($metadata['standardization_failed_rows'] ?? 0)) + $failedDelta,
                'standardization_total_rows' => $totalRows ?? ($metadata['standardization_total_rows'] ?? 0),
            ]),
        ]);
    }

    /**
     * @return array{
     *     processed: int,
     *     auto_approved: int,
     *     review_required: int,
     *     skipped: int,
     *     rejected: int
     * }
     */
    public function standardizeBatch(
        ImportBatch $batch,
        bool $onlyPending = false,
        ?int $limit = null,
        bool $persist = true,
    ): array {
        if (! $persist) {
            return $this->standardizeBatchDryRun($batch, $onlyPending, $limit);
        }

        $summary = $this->standardizeBatchWithProgress($batch, $onlyPending, $limit);

        unset($summary['failed']);

        return $summary;
    }

    /**
     * @return array{
     *     processed: int,
     *     auto_approved: int,
     *     review_required: int,
     *     skipped: int,
     *     rejected: int
     * }
     */
    protected function standardizeBatchDryRun(
        ImportBatch $batch,
        bool $onlyPending,
        ?int $limit,
    ): array {
        $query = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->orderBy('row_number');

        if ($onlyPending) {
            $query->where('standardization_status', StandardizationStatus::Pending->value);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $summary = [
            'processed' => 0,
            'auto_approved' => 0,
            'review_required' => 0,
            'skipped' => 0,
            'rejected' => 0,
        ];

        $this->matchIndex->warmupCaches();

        $query->chunkById(100, function (Collection $rows) use (&$summary): void {
            foreach ($rows as $row) {
                $result = $this->standardizeRow($row, persist: false);
                $summary['processed']++;
                $summary[$result['status_bucket']]++;
            }
        });

        $this->matchIndex->clearCache();

        return $summary;
    }

    /**
     * @return array{
     *     status: string,
     *     status_bucket: string,
     *     drug_confidence: float,
     *     company_confidence: float,
     *     tender_confidence: float,
     *     country_confidence: float,
     *     normalized_data: array<string, mixed>
     * }
     */
    public function standardizeRow(ImportRow $row, bool $persist = true): array
    {
        if ($this->shouldSkip($row)) {
            $status = $this->resolveSkipStatus($row);

            $payload = [
                'standardization_status' => $status,
                'drug_confidence' => 0,
                'company_confidence' => 0,
                'tender_confidence' => 0,
                'confidence_score' => 0,
            ];

            if ($persist) {
                $row->update($payload);
            }

            return [
                'status' => $status,
                'status_bucket' => $status === StandardizationStatus::Rejected->value ? 'rejected' : 'skipped',
                'drug_confidence' => 0.0,
                'company_confidence' => 0.0,
                'tender_confidence' => 0.0,
                'country_confidence' => 0.0,
                'normalized_data' => $row->normalized_data ?? [],
            ];
        }

        $country = $this->countryService->standardize($row);
        $company = $this->companyService->standardize($row, $persist, $country['country_id']);
        $drug = $this->drugService->standardize($row, $persist);
        $tender = $this->tenderService->standardize($row, $country);

        $normalizedData = array_merge($row->normalized_data ?? [], [
            'standardization' => [
                'country' => $country['normalized'],
                'company' => $company['normalized'],
                'drug' => $drug['normalized'],
                'tender' => $tender['normalized'],
                'tender_suggestion' => $tender['suggested'] ?? null,
                'company_suggestion' => $company['suggested'] ?? null,
                'drug_suggestion' => $drug['suggested'] ?? null,
                'review_items' => $this->buildReviewItems($row, $country, $company, $drug),
            ],
            'country_confidence' => $country['confidence'],
            'country_id' => $country['country_id'],
            'region_id' => $country['region_id'] ?? null,
            'drug_confidence_breakdown' => $drug['confidence_breakdown'] ?? null,
        ]);

        $status = $this->resolveStatus(
            $row->validation_status,
            $drug,
            $company,
            $drug['confidence'],
            $company['confidence'],
            $tender['confidence'],
            $country['confidence'],
            $country['review_required'] ?? false,
            $country['country_id'],
        );

        $overallConfidence = round(
            ($drug['confidence'] + $company['confidence'] + $tender['confidence'] + $country['confidence']) / 4,
            2
        );

        $update = [
            'normalized_data' => $normalizedData,
            'standardization_status' => $status,
            'drug_confidence' => $drug['confidence'],
            'company_confidence' => $company['confidence'],
            'tender_confidence' => $tender['confidence'],
            'confidence_score' => $overallConfidence,
            'standardized_drug_id' => $drug['standardized_drug_id'],
            'company_id' => $company['company_id'],
            'ai_assisted' => false,
        ];

        if ($persist) {
            $row->update($update);

            StandardizationLog::query()->create([
                'import_row_id' => $row->id,
                'entity_type' => 'import_row',
                'entity_id' => $row->id,
                'action' => 'standardized',
                'old_values' => null,
                'new_values' => [
                    'standardization_status' => $status,
                    'drug_confidence' => $drug['confidence'],
                    'company_confidence' => $company['confidence'],
                    'tender_confidence' => $tender['confidence'],
                    'country_confidence' => $country['confidence'],
                ],
                'source' => 'rules',
            ]);
        }

        return [
            'status' => $status,
            'status_bucket' => $this->statusBucket($status),
            'drug_confidence' => $drug['confidence'],
            'company_confidence' => $company['confidence'],
            'tender_confidence' => $tender['confidence'],
            'country_confidence' => $country['confidence'],
            'normalized_data' => $normalizedData,
        ];
    }

    public function updateBatchCounts(ImportBatch $batch): void
    {
        $rows = ImportRow::query()->where('import_batch_id', $batch->id);

        $metadata = array_merge($batch->metadata ?? [], [
            'auto_approved_rows' => (clone $rows)->where('standardization_status', StandardizationStatus::AutoApproved->value)->count(),
            'standardization_review_rows' => (clone $rows)->where('standardization_status', StandardizationStatus::ReviewRequired->value)->count(),
            'standardization_rejected_rows' => (clone $rows)->where('standardization_status', StandardizationStatus::Rejected->value)->count(),
            'standardization_skipped_rows' => (clone $rows)->where('standardization_status', StandardizationStatus::Skipped->value)->count(),
        ]);

        $batch->update(['metadata' => $metadata]);

        $this->importBatchService->refreshQualityScore($batch->fresh());
    }

    protected function shouldSkip(ImportRow $row): bool
    {
        if ($row->validation_status === ImportRowValidationStatus::Invalid->value) {
            return true;
        }

        if ($row->validation_status === ImportRowValidationStatus::Duplicate->value) {
            return true;
        }

        if (! $this->hasValidPriceUsd($row)) {
            return true;
        }

        if (! $this->hasRequiredAnalyticsFields($row)) {
            return true;
        }

        return false;
    }

    protected function resolveSkipStatus(ImportRow $row): string
    {
        if ($row->validation_status === ImportRowValidationStatus::Invalid->value
            || ! $this->hasValidPriceUsd($row)
            || ! $this->hasRequiredAnalyticsFields($row)) {
            return StandardizationStatus::Rejected->value;
        }

        return StandardizationStatus::Skipped->value;
    }

    protected function hasValidPriceUsd(ImportRow $row): bool
    {
        $price = $row->normalized_data['price_usd'] ?? null;

        return is_numeric($price) && (float) $price > 0;
    }

    protected function hasRequiredAnalyticsFields(ImportRow $row): bool
    {
        $hasDrug = filled($row->raw_code) || filled($row->raw_inn) || filled($row->raw_product_name);
        $hasCountry = filled($row->raw_country);
        $hasYear = filled($row->raw_year);

        return $hasDrug && $hasCountry && $hasYear;
    }

    protected function autoApproveThreshold(): float
    {
        return (float) config('import.auto_approve_threshold', 95);
    }

    /**
     * @param  array<string, mixed>  $drug
     * @param  array<string, mixed>  $company
     */
    protected function resolveStatus(
        string $validationStatus,
        array $drug,
        array $company,
        float $drugConfidence,
        float $companyConfidence,
        float $tenderConfidence,
        float $countryConfidence,
        bool $countryReviewRequired = false,
        ?int $countryId = null,
    ): string {
        $validatable = in_array($validationStatus, [
            ImportRowValidationStatus::Valid->value,
            ImportRowValidationStatus::Warning->value,
        ], true);

        if (! $validatable) {
            return StandardizationStatus::Rejected->value;
        }

        if ($countryId === null || $countryReviewRequired) {
            return StandardizationStatus::ReviewRequired->value;
        }

        if ($this->qualifiesForExactOrAliasAutoApprove($drug, $company)) {
            return StandardizationStatus::AutoApproved->value;
        }

        $minConfidence = min($drugConfidence, $companyConfidence, $tenderConfidence, $countryConfidence);

        if ($minConfidence >= $this->autoApproveThreshold()) {
            return StandardizationStatus::AutoApproved->value;
        }

        if ($drugConfidence >= $this->drugAutoMin()
            && $companyConfidence >= $this->companyAutoMin()
            && $tenderConfidence >= $this->tenderAutoMin()
            && $countryConfidence >= $this->countryAutoMin()) {
            return StandardizationStatus::AutoApproved->value;
        }

        return StandardizationStatus::ReviewRequired->value;
    }

    /**
     * @param  array<string, mixed>  $drug
     * @param  array<string, mixed>  $company
     */
    protected function qualifiesForExactOrAliasAutoApprove(array $drug, array $company): bool
    {
        $drugType = $drug['match_type'] ?? null;
        $companyType = $company['match_type'] ?? null;

        $drugStrong = in_array($drugType, ['exact_code', 'exact_alias'], true);
        $companyStrong = in_array($companyType, ['exact_name', 'exact_alias'], true);

        return $drugStrong && $companyStrong;
    }

    /**
     * @return list<array{entity: string, original: ?string, suggested: ?string, confidence: float, reason: ?string}>
     */
    protected function buildReviewItems(ImportRow $row, array $country, array $company, array $drug): array
    {
        $items = [];

        if (($country['review_required'] ?? false) || $country['country_id'] === null) {
            $items[] = [
                'entity' => 'country',
                'original' => $row->raw_country,
                'suggested' => $country['normalized']['canonical_name'] ?? $country['normalized']['suggested_name'] ?? null,
                'confidence' => $country['confidence'],
                'reason' => $country['reason'] ?? 'Country requires review',
            ];
        }

        if (($company['match_type'] ?? null) === 'country_mismatch' || ($company['match_type'] ?? null) === 'suggested') {
            $items[] = [
                'entity' => 'company',
                'original' => $row->raw_company_name ?? $row->raw_winner,
                'suggested' => $company['normalized']['canonical_name'] ?? $company['normalized']['suggested_name'] ?? null,
                'confidence' => $company['confidence'],
                'reason' => $company['reason'] ?? 'Company match requires review',
            ];
        }

        if (($drug['match_type'] ?? null) === 'suggested' || ($drug['match_type'] ?? null) === 'fuzzy_medium') {
            $items[] = [
                'entity' => 'drug',
                'original' => $row->raw_product_name ?? $row->raw_inn,
                'suggested' => $drug['normalized']['display_name'] ?? ($drug['suggested']['display_name'] ?? null),
                'confidence' => $drug['confidence'],
                'reason' => 'Drug match requires review',
                'confidence_breakdown' => $drug['confidence_breakdown'] ?? null,
            ];
        }

        return $items;
    }

    /**
     * Manually approve a review-required row.
     */
    public function approveRow(ImportRow $row, ?int $performedBy = null): void
    {
        $previousStatus = $row->standardization_status;

        $row->update(['standardization_status' => StandardizationStatus::Approved->value]);

        $this->logManualAction($row, 'approved_manually', $previousStatus, StandardizationStatus::Approved->value, $performedBy);
        $this->refreshBatchAfterManualAction($row);
    }

    /**
     * Reject a review-required row.
     */
    public function rejectRow(ImportRow $row, ?int $performedBy = null): void
    {
        $previousStatus = $row->standardization_status;

        $row->update(['standardization_status' => StandardizationStatus::Rejected->value]);

        $this->logManualAction($row, 'rejected_manually', $previousStatus, StandardizationStatus::Rejected->value, $performedBy);
        $this->refreshBatchAfterManualAction($row);
    }

    /**
     * Send approved or rejected rows back to the review queue.
     */
    public function sendBackToReview(ImportRow $row, ?int $performedBy = null): void
    {
        $previousStatus = $row->standardization_status;

        $normalized = $row->normalized_data ?? [];
        unset($normalized['standardization']['manual_review']);

        $row->update([
            'standardization_status' => StandardizationStatus::ReviewRequired->value,
            'normalized_data' => $normalized,
        ]);

        $this->logManualAction($row, 'sent_to_review', $previousStatus, StandardizationStatus::ReviewRequired->value, $performedBy);
        $this->refreshBatchAfterManualAction($row);
    }

    /**
     * Flag a row for manual review without changing its queue status.
     */
    public function markManualReview(ImportRow $row, ?int $performedBy = null): void
    {
        $normalized = $row->normalized_data ?? [];
        $normalized['standardization'] = array_merge($normalized['standardization'] ?? [], [
            'manual_review' => true,
            'manual_review_at' => now()->toIso8601String(),
        ]);

        $previousStatus = $row->standardization_status;

        $row->update([
            'standardization_status' => StandardizationStatus::ReviewRequired->value,
            'normalized_data' => $normalized,
        ]);

        $this->logManualAction($row, 'marked_manual_review', $previousStatus, StandardizationStatus::ReviewRequired->value, $performedBy);
        $this->refreshBatchAfterManualAction($row);
    }

    /**
     * @param  list<int>  $rowIds
     * @return array{processed: int, skipped: int}
     */
    public function bulkAction(string $action, array $rowIds, ?int $performedBy = null): array
    {
        $processed = 0;
        $skipped = 0;

        ImportRow::query()
            ->whereIn('id', $rowIds)
            ->orderBy('id')
            ->chunkById(100, function (Collection $rows) use ($action, $performedBy, &$processed, &$skipped): void {
                foreach ($rows as $row) {
                    if (! $this->applyBulkAction($row, $action, $performedBy)) {
                        $skipped++;

                        continue;
                    }

                    $processed++;
                }
            });

        return ['processed' => $processed, 'skipped' => $skipped];
    }

    protected function applyBulkAction(ImportRow $row, string $action, ?int $performedBy): bool
    {
        return match ($action) {
            'approve' => $this->tryApproveRow($row, $performedBy),
            'reject' => $this->tryRejectRow($row, $performedBy),
            'send_to_review' => $this->trySendBackToReview($row, $performedBy),
            'manual_review' => $this->tryMarkManualReview($row, $performedBy),
            default => false,
        };
    }

    protected function tryApproveRow(ImportRow $row, ?int $performedBy): bool
    {
        if ($row->standardization_status !== StandardizationStatus::ReviewRequired->value) {
            return false;
        }

        $this->approveRow($row, $performedBy);

        return true;
    }

    protected function tryRejectRow(ImportRow $row, ?int $performedBy): bool
    {
        if ($row->standardization_status !== StandardizationStatus::ReviewRequired->value) {
            return false;
        }

        $this->rejectRow($row, $performedBy);

        return true;
    }

    protected function trySendBackToReview(ImportRow $row, ?int $performedBy): bool
    {
        if (! in_array($row->standardization_status, [
            StandardizationStatus::Approved->value,
            StandardizationStatus::AutoApproved->value,
            StandardizationStatus::Rejected->value,
        ], true)) {
            return false;
        }

        $this->sendBackToReview($row, $performedBy);

        return true;
    }

    protected function tryMarkManualReview(ImportRow $row, ?int $performedBy): bool
    {
        $this->markManualReview($row, $performedBy);

        return true;
    }

    /**
     * Apply a manual correction to drug or company match.
     *
     * @param  array{entity: string, standardized_drug_id?: int, company_id?: int}  $payload
     */
    public function editMatch(ImportRow $row, array $payload, ?int $performedBy = null): void
    {
        $entity = $payload['entity'];
        $normalized = $row->normalized_data ?? [];
        $std = $normalized['standardization'] ?? [];

        if ($entity === 'drug' && ! empty($payload['standardized_drug_id'])) {
            $drug = StandardizedDrug::query()->findOrFail($payload['standardized_drug_id']);

            $row->standardized_drug_id = $drug->id;
            $row->drug_confidence = 100.0;

            $std['drug'] = array_merge($std['drug'] ?? [], [
                'display_name' => $drug->display_name,
                'standardized_drug_id' => $drug->id,
                'match_type' => 'manual',
            ]);
            $std['drug_suggestion'] = [
                'id' => $drug->id,
                'display_name' => $drug->display_name,
            ];
            $std['review_items'] = $this->removeReviewItem($std['review_items'] ?? [], 'drug');
        }

        if ($entity === 'company' && ! empty($payload['company_id'])) {
            $company = Company::query()->findOrFail($payload['company_id']);

            $row->company_id = $company->id;
            $row->company_confidence = 100.0;

            $std['company'] = array_merge($std['company'] ?? [], [
                'canonical_name' => $company->name,
                'company_id' => $company->id,
                'match_type' => 'manual',
            ]);
            $std['company_suggestion'] = [
                'id' => $company->id,
                'name' => $company->name,
            ];
            $std['review_items'] = $this->removeReviewItem($std['review_items'] ?? [], 'company');
        }

        unset($std['manual_review']);

        $normalized['standardization'] = $std;
        $row->normalized_data = $normalized;

        $row->confidence_score = round(
            ((float) $row->drug_confidence + (float) $row->company_confidence + (float) $row->tender_confidence
                + (float) ($normalized['country_confidence'] ?? 0)) / 4,
            2
        );

        if (empty($std['review_items'])) {
            $row->standardization_status = StandardizationStatus::Approved->value;
        }

        $row->save();

        StandardizationLog::query()->create([
            'import_row_id' => $row->id,
            'entity_type' => $entity,
            'entity_id' => $payload['standardized_drug_id'] ?? $payload['company_id'] ?? $row->id,
            'action' => 'manual_correction',
            'old_values' => null,
            'new_values' => $payload,
            'source' => 'manual',
            'performed_by' => $performedBy,
        ]);

        $this->refreshBatchAfterManualAction($row);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    protected function removeReviewItem(array $items, string $entity): array
    {
        return array_values(array_filter($items, fn (array $item) => ($item['entity'] ?? '') !== $entity));
    }

    /**
     * One-click operational approve: all review_required rows for a batch.
     *
     * @return int Number of rows updated
     */
    public function approveAllReviewRequiredForBatch(ImportBatch $batch, ?int $performedBy = null): int
    {
        $approved = 0;

        ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->where('standardization_status', StandardizationStatus::ReviewRequired->value)
            ->orderBy('id')
            ->chunkById(100, function (Collection $rows) use (&$approved): void {
                $ids = $rows->pluck('id');

                $approved += ImportRow::query()
                    ->whereIn('id', $ids)
                    ->where('standardization_status', StandardizationStatus::ReviewRequired->value)
                    ->update(['standardization_status' => StandardizationStatus::Approved->value]);
            });

        if ($approved > 0) {
            StandardizationLog::query()->create([
                'import_row_id' => null,
                'entity_type' => 'import_batch',
                'entity_id' => $batch->id,
                'action' => 'bulk_approve_all',
                'old_values' => [
                    'standardization_status' => StandardizationStatus::ReviewRequired->value,
                ],
                'new_values' => [
                    'batch_id' => $batch->id,
                    'approved_count' => $approved,
                    'standardization_status' => StandardizationStatus::Approved->value,
                ],
                'source' => 'manual',
                'performed_by' => $performedBy,
            ]);

            $this->updateBatchCounts($batch->fresh());
            app(ImportPipelineOrchestratorService::class)->onReviewQueueUpdated($batch->fresh());
        }

        return $approved;
    }

    protected function logManualAction(
        ImportRow $row,
        string $action,
        string $previousStatus,
        string $newStatus,
        ?int $performedBy,
    ): void {
        StandardizationLog::query()->create([
            'import_row_id' => $row->id,
            'entity_type' => 'import_row',
            'entity_id' => $row->id,
            'action' => $action,
            'old_values' => ['standardization_status' => $previousStatus],
            'new_values' => ['standardization_status' => $newStatus],
            'source' => 'manual',
            'performed_by' => $performedBy,
        ]);
    }

    protected function refreshBatchAfterManualAction(ImportRow $row): void
    {
        if ($row->import_batch_id) {
            $batch = ImportBatch::query()->find($row->import_batch_id);

            if ($batch) {
                $this->updateBatchCounts($batch);
                app(ImportPipelineOrchestratorService::class)->onReviewQueueUpdated($batch->fresh());
            }
        }
    }

    protected function statusBucket(string $status): string
    {
        return match ($status) {
            StandardizationStatus::AutoApproved->value => 'auto_approved',
            StandardizationStatus::ReviewRequired->value => 'review_required',
            StandardizationStatus::Rejected->value => 'rejected',
            default => 'skipped',
        };
    }
}
