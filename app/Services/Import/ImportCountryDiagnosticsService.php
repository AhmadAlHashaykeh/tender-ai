<?php

namespace App\Services\Import;

use App\Models\Country;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Region;
use App\Services\Materialization\MaterializationEligibilityService;
use App\Services\Standardization\CountryStandardizationService;

class ImportCountryDiagnosticsService
{
    public function __construct(
        protected CountryStandardizationService $countryService,
        protected MaterializationEligibilityService $eligibility,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forBatch(ImportBatch $batch, int $exampleLimit = 10): array
    {
        $rows = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->whereNotNull('raw_country')
            ->where('raw_country', '!=', '')
            ->orderBy('row_number')
            ->get();

        $rawCounts = [];
        $unmapped = [];
        $regionOnly = [];
        $mapped = 0;
        $missingCountryId = 0;
        $examples = [];

        foreach ($rows as $row) {
            $raw = trim((string) $row->raw_country);
            $rawCounts[$raw] = ($rawCounts[$raw] ?? 0) + 1;

            $normalized = $row->normalized_data ?? [];
            $countryId = $normalized['country_id'] ?? null;
            $regionId = $normalized['region_id']
                ?? ($normalized['standardization']['country']['region_id'] ?? null);
            $stdCountry = $normalized['standardization']['country'] ?? [];

            if ($countryId === null || $countryId === '') {
                $missingCountryId++;
            } else {
                $mapped++;
            }

            $preview = $this->countryService->standardize($row);
            $wouldMap = $preview['country_id'] !== null;
            $wouldRegion = ($preview['region_id'] ?? null) !== null && ! $wouldMap;

            if (! $wouldMap && ! $wouldRegion) {
                $unmapped[$raw] = ($unmapped[$raw] ?? 0) + 1;
            }

            if ($wouldRegion || (($regionId !== null && ($countryId === null || $countryId === '')))) {
                $regionOnly[$raw] = ($regionOnly[$raw] ?? 0) + 1;
            }

            if (count($examples) < $exampleLimit) {
                $examples[] = [
                    'row_id' => $row->id,
                    'row_number' => $row->row_number,
                    'raw_country' => $raw,
                    'normalized_name' => $stdCountry['normalized_name'] ?? $preview['normalized']['normalized_name'] ?? null,
                    'canonical_name' => $stdCountry['canonical_name'] ?? $preview['normalized']['canonical_name'] ?? null,
                    'stored_country_id' => $countryId,
                    'stored_region_id' => $regionId,
                    'preview_country_id' => $preview['country_id'],
                    'preview_region_id' => $preview['region_id'] ?? null,
                    'match_type' => $preview['match_type'] ?? null,
                    'materialization_block' => $this->eligibility->ineligibilityReason($row),
                ];
            }
        }

        arsort($rawCounts);
        arsort($unmapped);
        arsort($regionOnly);

        return [
            'batch_id' => $batch->id,
            'rows_with_country' => $rows->count(),
            'rows_with_country_id' => $mapped,
            'rows_missing_country_id' => $missingCountryId,
            'raw_country_counts' => $rawCounts,
            'unmapped_raw_values' => $unmapped,
            'region_only_raw_values' => $regionOnly,
            'top_unmapped' => array_slice(array_keys($unmapped), 0, 10),
            'examples' => $examples,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryForBatch(ImportBatch $batch): array
    {
        $report = $this->forBatch($batch, 0);

        $skipMissing = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->get()
            ->filter(function (ImportRow $row) {
                $reason = $row->normalized_data['materialization_skip_reason'] ?? null;

                return in_array($reason, [
                    MaterializationEligibilityService::REASON_MISSING_COUNTRY,
                    MaterializationEligibilityService::REASON_REGION_REQUIRES_COUNTRY,
                ], true);
            })
            ->count();

        return [
            'missing_country_id' => $report['rows_missing_country_id'],
            'top_unmapped' => $report['top_unmapped'],
            'region_only_values' => array_slice(array_keys($report['region_only_raw_values']), 0, 10),
            'materialization_skipped_for_country' => $skipMissing,
            'can_repair' => $report['rows_with_country'] > 0,
        ];
    }

    public function countryName(?int $countryId): ?string
    {
        if ($countryId === null) {
            return null;
        }

        return Country::query()->find($countryId)?->name;
    }

    public function regionName(?int $regionId): ?string
    {
        if ($regionId === null) {
            return null;
        }

        return Region::query()->find($regionId)?->name;
    }
}
