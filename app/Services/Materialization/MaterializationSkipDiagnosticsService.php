<?php

namespace App\Services\Materialization;

use App\Models\ImportBatch;
use App\Models\ImportRow;

class MaterializationSkipDiagnosticsService
{
    public function __construct(
        protected MaterializationEligibilityService $eligibility,
        protected ImportMaterializationService $materialization,
    ) {}

    /**
     * @return array{
     *     batch_id: int,
     *     rows_checked: int,
     *     eligible_count: int,
     *     materialized_count: int,
     *     skip_reasons: array<string, int>,
     *     examples: list<array<string, mixed>>
     * }
     */
    public function forBatch(ImportBatch $batch, int $exampleLimit = 5): array
    {
        $stats = $this->materialization->batchMaterializationStats($batch);
        $rows = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->orderBy('row_number')
            ->get();

        $skipReasons = [];
        $examples = [];

        foreach ($rows as $row) {
            $reason = $this->eligibility->ineligibilityReason($row);

            if ($reason === null) {
                continue;
            }

            $skipReasons[$reason] = ($skipReasons[$reason] ?? 0) + 1;

            if (count($examples) < $exampleLimit) {
                $examples[] = $this->exampleRow($row, $reason);
            }
        }

        ksort($skipReasons);

        return [
            'batch_id' => $batch->id,
            'rows_checked' => $rows->count(),
            'eligible_count' => $stats['eligible_pending'],
            'materialized_count' => $stats['materialized'],
            'skip_reasons' => $skipReasons,
            'examples' => $examples,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function exampleRow(ImportRow $row, string $reason): array
    {
        $normalized = $row->normalized_data ?? [];
        $std = $normalized['standardization'] ?? [];

        return [
            'row_id' => $row->id,
            'row_number' => $row->row_number,
            'skip_reason' => $reason,
            'skip_details' => MaterializationEligibilityService::reasonLabels()[$reason] ?? $reason,
            'validation_status' => $row->validation_status,
            'standardization_status' => $row->standardization_status,
            'original' => [
                'raw_code' => $row->raw_code,
                'raw_inn' => $row->raw_inn,
                'raw_product_name' => $row->raw_product_name,
                'raw_country' => $row->raw_country,
                'raw_company_name' => $row->raw_company_name,
                'raw_winner' => $row->raw_winner,
                'raw_tender_number' => $row->raw_tender_number,
                'raw_price_usd' => $row->raw_price_usd,
            ],
            'standardized' => [
                'country_id' => $normalized['country_id'] ?? null,
                'price_usd' => $normalized['price_usd'] ?? null,
                'tender_number' => $std['tender']['tender_number'] ?? null,
                'drug_id' => $row->standardized_drug_id,
                'company_id' => $row->company_id,
            ],
            'stored_skip' => [
                'reason' => $normalized['materialization_skip_reason'] ?? null,
                'details' => $normalized['materialization_skip_details'] ?? null,
            ],
        ];
    }
}
