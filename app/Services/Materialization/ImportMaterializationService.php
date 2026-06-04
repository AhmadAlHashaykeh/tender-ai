<?php

namespace App\Services\Materialization;

use App\Enums\ImportRowValidationStatus;
use App\Enums\StandardizationStatus;
use App\Models\BidRecord;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Services\Import\ImportBatchService;
use Illuminate\Support\Facades\DB;

class ImportMaterializationService
{
    public function __construct(
        protected CompanyMaterializationService $companyService,
        protected DrugMaterializationService $drugService,
        protected TenderMaterializationService $tenderService,
        protected TenderItemMaterializationService $tenderItemService,
        protected BidRecordMaterializationService $bidRecordService,
        protected ImportBatchService $importBatchService,
        protected MaterializationEligibilityService $eligibility,
    ) {}

    /**
     * @return array{
     *     processed: int,
     *     materialized: int,
     *     skipped: int,
     *     failed: int,
     *     companies_created: int,
     *     drugs_created: int,
     *     tenders_created: int,
     *     tender_items_created: int,
     *     bid_records_created: int
     * }
     */
    public function materializeBatch(
        ImportBatch $batch,
        bool $onlyApproved = true,
        ?int $limit = null,
        bool $persist = true,
        bool $retrySkipped = false,
    ): array {
        $summary = $this->emptySummary();

        $query = $this->eligibleQuery($batch->id, $onlyApproved, $retrySkipped);

        if ($limit !== null) {
            $query->limit($limit);
        }

        $query->orderBy('row_number')->chunkById(50, function ($rows) use (&$summary, $persist): void {
            foreach ($rows as $row) {
                $summary['processed']++;
                $outcome = $this->materializeRow($row, $persist);
                $summary[$outcome['bucket']]++;
                $summary['companies_created'] += $outcome['companies_created'];
                $summary['drugs_created'] += $outcome['drugs_created'];
                $summary['tenders_created'] += $outcome['tenders_created'];
                $summary['tender_items_created'] += $outcome['tender_items_created'];
                $summary['bid_records_created'] += $outcome['bid_records_created'];

                if (isset($outcome['skip_reason'])) {
                    $key = $outcome['skip_reason'];
                    $summary['skip_reasons'][$key] = ($summary['skip_reasons'][$key] ?? 0) + 1;
                }
            }
        });

        if ($persist) {
            $this->updateBatchCounts($batch);
            $this->syncBatchMaterializationMetadata($batch->fresh(), $summary);
        }

        return $summary;
    }

    /**
     * @return array{
     *     bucket: string,
     *     companies_created: int,
     *     drugs_created: int,
     *     tenders_created: int,
     *     tender_items_created: int,
     *     bid_records_created: int,
     *     skip_reason?: string,
     *     skip_details?: string
     * }
     */
    public function materializeRow(
        ImportRow $row,
        bool $persist = true,
        ?MaterializationLookupCache $cache = null,
    ): array {
        $ineligibleReason = $this->eligibility->ineligibilityReason($row);

        if ($ineligibleReason !== null) {
            if ($cache !== null && $ineligibleReason === MaterializationEligibilityService::REASON_ALREADY_MATERIALIZED) {
                $cache->markRowMaterialized($row->id);
            }

            return $this->skippedOutcome($row, $ineligibleReason, $persist);
        }

        if (! $persist) {
            return [
                'bucket' => 'materialized',
                'companies_created' => 1,
                'drugs_created' => 1,
                'tenders_created' => 1,
                'tender_items_created' => 1,
                'bid_records_created' => 1,
            ];
        }

        $counts = [
            'companies_created' => 0,
            'drugs_created' => 0,
            'tenders_created' => 0,
            'tender_items_created' => 0,
            'bid_records_created' => 0,
        ];

        try {
            DB::transaction(function () use ($row, &$counts, $cache): void {
                $countryId = $this->eligibility->resolveCountryId($row);

                if ($countryId === null) {
                    throw new \RuntimeException('Country could not be resolved for materialization.');
                }

                $company = $this->companyService->resolve($row, $countryId, $cache);
                $counts['companies_created'] += $company['created'] ? 1 : 0;

                $drug = $this->drugService->resolve($row, $cache);
                $counts['drugs_created'] += $drug['created'] ? 1 : 0;

                $tender = $this->tenderService->resolve($row, $countryId, $cache);
                $counts['tenders_created'] += $tender['created'] ? 1 : 0;

                $item = $this->tenderItemService->resolve($row, $tender['tender_id'], $drug['standardized_drug_id']);
                $counts['tender_items_created'] += $item['created'] ? 1 : 0;

                $bid = $this->bidRecordService->resolve(
                    $row,
                    $item['tender_item_id'],
                    $company['company_id'],
                    $drug['standardized_drug_id'],
                    $tender['tender_id'],
                    $countryId,
                    $cache,
                );
                $counts['bid_records_created'] += $bid['created'] ? 1 : 0;

                $normalized = $row->normalized_data ?? [];
                $normalized['materialization_status'] = 'materialized';
                $normalized['materialized_at'] = now()->toIso8601String();
                unset($normalized['materialization_error']);

                $row->update([
                    'standardized_drug_id' => $drug['standardized_drug_id'],
                    'company_id' => $company['company_id'],
                    'tender_id' => $tender['tender_id'],
                    'tender_item_id' => $item['tender_item_id'],
                    'bid_record_id' => $bid['bid_record_id'],
                    'row_type' => 'winning_bid',
                    'normalized_data' => $normalized,
                ]);

                $cache?->markRowMaterialized($row->id);
            });

            return array_merge(['bucket' => 'materialized'], $counts);
        } catch (\Throwable $exception) {
            $normalized = $row->normalized_data ?? [];
            $normalized['materialization_status'] = 'failed';
            $normalized['materialization_error'] = $exception->getMessage();

            $row->update([
                'error_message' => $row->error_message
                    ? $row->error_message.' | Materialization: '.$exception->getMessage()
                    : 'Materialization: '.$exception->getMessage(),
                'normalized_data' => $normalized,
            ]);

            return array_merge(['bucket' => 'failed'], $counts);
        }
    }

    public function isEligible(ImportRow $row): bool
    {
        return $this->eligibility->isEligible($row);
    }

    public function isAlreadyMaterialized(ImportRow $row, ?MaterializationLookupCache $cache = null): bool
    {
        if ($cache !== null && $cache->isRowMaterialized($row->id)) {
            return true;
        }

        return $this->eligibility->isAlreadyMaterialized($row);
    }

    public function resolveCountryId(ImportRow $row, bool $throw = true): ?int
    {
        $countryId = $this->eligibility->resolveCountryId($row);

        if ($countryId === null && $throw) {
            throw new \RuntimeException('Country could not be resolved for materialization.');
        }

        return $countryId;
    }

    /**
     * @return array{bucket: string, companies_created: int, drugs_created: int, tenders_created: int, tender_items_created: int, bid_records_created: int, skip_reason: string, skip_details: string}
     */
    protected function skippedOutcome(ImportRow $row, string $reason, bool $persist): array
    {
        $payload = $this->eligibility->skipPayload($row);
        $payload['reason'] = $reason;

        if ($persist) {
            $normalized = $row->normalized_data ?? [];
            $normalized['materialization_status'] = 'skipped';
            $normalized['materialization_skip_reason'] = $payload['reason'];
            $normalized['materialization_skip_details'] = $payload['details'];
            $normalized['materialization_skipped_at'] = now()->toIso8601String();
            unset($normalized['materialization_error']);

            $row->update(['normalized_data' => $normalized]);
        }

        return [
            'bucket' => 'skipped',
            'companies_created' => 0,
            'drugs_created' => 0,
            'tenders_created' => 0,
            'tender_items_created' => 0,
            'bid_records_created' => 0,
            'skip_reason' => $payload['reason'],
            'skip_details' => $payload['details'],
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function syncBatchMaterializationMetadata(ImportBatch $batch, array $summary): void
    {
        $stats = $this->batchMaterializationStats($batch);
        $bidCount = (int) BidRecord::query()->where('import_batch_id', $batch->id)->count();

        $status = $stats['eligible_pending'] > 0 ? 'incomplete' : 'completed';

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'materialization_status' => $status,
                'materialization_completed_at' => now()->toIso8601String(),
                'materialization_processed_rows' => (int) ($summary['processed'] ?? 0),
                'materialization_materialized_rows' => (int) ($summary['materialized'] ?? 0),
                'materialization_skipped_rows' => (int) ($summary['skipped'] ?? 0),
                'materialization_failed_rows' => (int) ($summary['failed'] ?? 0),
                'materialization_skip_reasons' => $summary['skip_reasons'] ?? [],
                'materialization_summary' => [
                    'processed' => (int) ($summary['processed'] ?? 0),
                    'materialized' => (int) ($summary['materialized'] ?? 0),
                    'skipped' => (int) ($summary['skipped'] ?? 0),
                    'failed' => (int) ($summary['failed'] ?? 0),
                ],
                'materialization_last_error' => $status === 'incomplete'
                    ? 'Some approved rows were not materialized. Run imports:diagnose-materialization for details.'
                    : ($summary['failed'] > 0
                        ? sprintf('%d row(s) failed during materialization.', $summary['failed'])
                        : null),
            ]),
        ]);
    }

    /**
     * @return array{
     *     materialized: int,
     *     eligible_pending: int,
     *     pending_standardization: int,
     *     awaiting_review: int,
     *     ineligible: int,
     *     failed: int
     * }
     */
    public function batchMaterializationStats(ImportBatch $batch): array
    {
        $batchId = $batch->id;
        $valid = ImportRowValidationStatus::Valid->value;
        $warning = ImportRowValidationStatus::Warning->value;
        $pending = StandardizationStatus::Pending->value;
        $auto = StandardizationStatus::AutoApproved->value;
        $approved = StandardizationStatus::Approved->value;
        $review = StandardizationStatus::ReviewRequired->value;

        $materializationFailedExpr = $this->jsonEqualsExpression('materialization_status', 'failed');

        $base = DB::table('import_rows')->where('import_batch_id', $batchId);

        $materialized = (clone $base)
            ->where(function ($query) {
                $query->whereNotNull('bid_record_id')
                    ->orWhereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('bid_records')
                            ->whereColumn('bid_records.source_import_row_id', 'import_rows.id');
                    });
            })
            ->count();

        $failed = (clone $base)->whereRaw($materializationFailedExpr)->count();

        $pendingStandardization = (clone $base)
            ->where('standardization_status', $pending)
            ->whereIn('validation_status', [$valid, $warning])
            ->count();

        $awaitingReview = (clone $base)
            ->where('standardization_status', $review)
            ->count();

        $eligiblePending = $this->eligibility->constrainEligible(
            ImportRow::query()->where('import_batch_id', $batchId),
        )->count();

        $total = (int) ($batch->row_count ?: (clone $base)->count());
        $ineligible = max(0, $total - $materialized - $failed - $eligiblePending - $pendingStandardization - $awaitingReview);

        return [
            'materialized' => $materialized,
            'eligible_pending' => $eligiblePending,
            'pending_standardization' => $pendingStandardization,
            'awaiting_review' => $awaitingReview,
            'ineligible' => $ineligible,
            'failed' => $failed,
        ];
    }

    protected function jsonPathExpression(string $path): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "json_extract(normalized_data, '$.{$path}')",
            default => "JSON_UNQUOTE(JSON_EXTRACT(normalized_data, '$.{$path}'))",
        };
    }

    protected function jsonNumericExpression(string $path): string
    {
        $driver = DB::connection()->getDriverName();
        $extract = $this->jsonPathExpression($path);

        return match ($driver) {
            'sqlite' => "CAST({$extract} AS REAL)",
            default => "CAST({$extract} AS DECIMAL(20,6))",
        };
    }

    protected function jsonEqualsExpression(string $path, string $value): string
    {
        $driver = DB::connection()->getDriverName();
        $extract = $this->jsonPathExpression($path);

        return match ($driver) {
            'sqlite' => "{$extract} = '{$value}'",
            default => "{$extract} = '{$value}'",
        };
    }

    protected function isPendingStandardization(ImportRow $row): bool
    {
        if ($row->standardization_status !== StandardizationStatus::Pending->value) {
            return false;
        }

        return in_array($row->validation_status, [
            ImportRowValidationStatus::Valid->value,
            ImportRowValidationStatus::Warning->value,
        ], true);
    }

    public function updateBatchCounts(ImportBatch $batch): void
    {
        $materialized = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->whereNotNull('bid_record_id')
            ->count();

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'materialized_rows' => $materialized,
                'persisted_rows' => $materialized,
            ]),
        ]);

        $this->importBatchService->refreshQualityScore($batch->fresh());
    }

    public function clearStaleSkipMarkers(ImportBatch $batch): int
    {
        $cleared = 0;

        $this->eligibility->constrainEligible(
            ImportRow::query()->where('import_batch_id', $batch->id),
        )->chunkById(100, function ($rows) use (&$cleared): void {
            foreach ($rows as $row) {
                $normalized = $row->normalized_data ?? [];

                if (($normalized['materialization_status'] ?? '') !== 'skipped') {
                    continue;
                }

                unset(
                    $normalized['materialization_status'],
                    $normalized['materialization_skip_reason'],
                    $normalized['materialization_skip_details'],
                    $normalized['materialization_skipped_at'],
                );

                $row->update(['normalized_data' => $normalized]);
                $cleared++;
            }
        });

        return $cleared;
    }

    /**
     * @return array<string, int>
     */
    public function aggregateSkipReasons(ImportBatch $batch): array
    {
        $counts = [];

        ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->orderBy('row_number')
            ->chunkById(200, function ($rows) use (&$counts): void {
                foreach ($rows as $row) {
                    $reason = $row->normalized_data['materialization_skip_reason'] ?? null;

                    if ($reason === null) {
                        $reason = $this->eligibility->ineligibilityReason($row);
                    }

                    if ($reason === null) {
                        continue;
                    }

                    $counts[$reason] = ($counts[$reason] ?? 0) + 1;
                }
            });

        ksort($counts);

        return $counts;
    }

    protected function eligibleQuery(int $batchId, bool $onlyApproved, bool $retrySkipped = false)
    {
        $query = ImportRow::query()->where('import_batch_id', $batchId);

        if ($onlyApproved) {
            $this->eligibility->constrainEligible($query);
        } else {
            $query->whereIn('validation_status', [
                ImportRowValidationStatus::Valid->value,
                ImportRowValidationStatus::Warning->value,
            ]);
        }

        return $query;
    }

    /**
     * @return array{
     *     processed: int,
     *     materialized: int,
     *     skipped: int,
     *     failed: int,
     *     companies_created: int,
     *     drugs_created: int,
     *     tenders_created: int,
     *     tender_items_created: int,
     *     bid_records_created: int
     * }
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
            'skip_reasons' => [],
        ];
    }
}
