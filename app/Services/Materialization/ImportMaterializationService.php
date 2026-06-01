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
    ): array {
        $summary = $this->emptySummary();

        $query = $this->eligibleQuery($batch->id, $onlyApproved);

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
            }
        });

        if ($persist) {
            $this->updateBatchCounts($batch);
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
     *     bid_records_created: int
     * }
     */
    public function materializeRow(
        ImportRow $row,
        bool $persist = true,
        ?MaterializationLookupCache $cache = null,
    ): array {
        $noop = [
            'bucket' => 'skipped',
            'companies_created' => 0,
            'drugs_created' => 0,
            'tenders_created' => 0,
            'tender_items_created' => 0,
            'bid_records_created' => 0,
        ];

        if (! $this->isEligible($row)) {
            return $noop;
        }

        if ($this->isAlreadyMaterialized($row, $cache)) {
            return $noop;
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
                $countryId = $this->resolveCountryId($row);

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
        if (! in_array($row->validation_status, [
            ImportRowValidationStatus::Valid->value,
            ImportRowValidationStatus::Warning->value,
        ], true)) {
            return false;
        }

        if (! in_array($row->standardization_status, [
            StandardizationStatus::AutoApproved->value,
            StandardizationStatus::Approved->value,
        ], true)) {
            return false;
        }

        $priceUsd = $row->normalized_data['price_usd'] ?? null;
        if (! is_numeric($priceUsd) || (float) $priceUsd <= 0) {
            return false;
        }

        if ($this->resolveCountryId($row, false) === null) {
            return false;
        }

        if (! filled($row->raw_code) && ! filled($row->raw_inn) && ! filled($row->raw_product_name)) {
            return false;
        }

        if (! filled($row->raw_company_name) && ! filled($row->raw_winner)) {
            return false;
        }

        $tenderNumber = $row->normalized_data['standardization']['tender']['tender_number'] ?? $row->raw_tender_number;

        return filled($tenderNumber);
    }

    public function isAlreadyMaterialized(ImportRow $row, ?MaterializationLookupCache $cache = null): bool
    {
        if ($row->bid_record_id !== null) {
            return true;
        }

        if ($cache !== null && $cache->isRowMaterialized($row->id)) {
            return true;
        }

        return BidRecord::query()->where('source_import_row_id', $row->id)->exists();
    }

    public function resolveCountryId(ImportRow $row, bool $throw = true): ?int
    {
        $countryId = $row->normalized_data['country_id'] ?? null;

        if ($countryId !== null) {
            return (int) $countryId;
        }

        if ($throw) {
            throw new \RuntimeException('Country could not be resolved for materialization.');
        }

        return null;
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
        $priceUsdExpr = $this->jsonNumericExpression('price_usd');
        $countryIdExpr = $this->jsonPathExpression('country_id');

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

        $eligiblePending = (clone $base)
            ->whereIn('validation_status', [$valid, $warning])
            ->whereIn('standardization_status', [$auto, $approved])
            ->whereNull('bid_record_id')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('bid_records')
                    ->whereColumn('bid_records.source_import_row_id', 'import_rows.id');
            })
            ->whereRaw("{$priceUsdExpr} > 0")
            ->whereRaw("{$countryIdExpr} IS NOT NULL")
            ->where(function ($query) {
                $query->whereNotNull('raw_code')
                    ->orWhereNotNull('raw_inn')
                    ->orWhereNotNull('raw_product_name');
            })
            ->where(function ($query) {
                $query->whereNotNull('raw_company_name')
                    ->orWhereNotNull('raw_winner');
            })
            ->where(function ($query) {
                $query->whereNotNull('raw_tender_number')
                    ->orWhereRaw($this->jsonPathExpression('standardization.tender.tender_number').' IS NOT NULL');
            })
            ->count();

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

    protected function eligibleQuery(int $batchId, bool $onlyApproved)
    {
        $query = ImportRow::query()->where('import_batch_id', $batchId);

        if ($onlyApproved) {
            $query->whereIn('standardization_status', [
                StandardizationStatus::AutoApproved->value,
                StandardizationStatus::Approved->value,
            ]);
        }

        $query->whereIn('validation_status', [
            ImportRowValidationStatus::Valid->value,
            ImportRowValidationStatus::Warning->value,
        ]);

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
        ];
    }
}
