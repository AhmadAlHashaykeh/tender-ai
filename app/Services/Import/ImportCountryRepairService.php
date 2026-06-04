<?php

namespace App\Services\Import;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Services\Materialization\MaterializationEligibilityService;
use App\Services\Standardization\CountryStandardizationService;

class ImportCountryRepairService
{
    public function __construct(
        protected CountryStandardizationService $countryService,
        protected MaterializationEligibilityService $eligibility,
    ) {}

    /**
     * Re-run country standardization for batch rows without changing drug/company matches.
     *
     * @return array{
     *     processed: int,
     *     country_mapped: int,
     *     region_only: int,
     *     still_unmapped: int,
     *     skip_cleared: int
     * }
     */
    public function repairBatch(ImportBatch $batch): array
    {
        CountryStandardizationService::clearCache();

        $processed = 0;
        $countryMapped = 0;
        $regionOnly = 0;
        $stillUnmapped = 0;
        $skipCleared = 0;

        ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->orderBy('row_number')
            ->chunkById(200, function ($rows) use (
                &$processed,
                &$countryMapped,
                &$regionOnly,
                &$stillUnmapped,
                &$skipCleared,
            ) {
                foreach ($rows as $row) {
                    if (! filled(trim((string) $row->raw_country))) {
                        continue;
                    }

                    $processed++;
                    $country = $this->countryService->standardize($row);
                    $normalized = $row->normalized_data ?? [];

                    $before = $normalized;
                    $normalized['country_id'] = $country['country_id'];
                    $normalized['region_id'] = $country['region_id'] ?? null;
                    $normalized['country_confidence'] = $country['confidence'];

                    $std = $normalized['standardization'] ?? [];
                    $std['country'] = $country['normalized'];
                    $normalized['standardization'] = $std;

                    if ($country['country_id'] !== null) {
                        $countryMapped++;
                    } elseif (($country['region_id'] ?? null) !== null) {
                        $regionOnly++;
                    } else {
                        $stillUnmapped++;
                    }

                    $skipReason = $before['materialization_skip_reason'] ?? null;
                    $countrySkipReasons = [
                        MaterializationEligibilityService::REASON_MISSING_COUNTRY,
                        MaterializationEligibilityService::REASON_REGION_REQUIRES_COUNTRY,
                    ];

                    if ($country['country_id'] !== null
                        && in_array($skipReason, $countrySkipReasons, true)) {
                        unset(
                            $normalized['materialization_skip_reason'],
                            $normalized['materialization_skip_details'],
                            $normalized['materialization_skipped_at'],
                        );
                        if (($normalized['materialization_status'] ?? '') === 'skipped') {
                            unset($normalized['materialization_status']);
                        }
                        $skipCleared++;
                    }

                    if ($this->normalizedCountrySliceChanged($before, $normalized)) {
                        $row->update(['normalized_data' => $normalized]);
                    }
                }
            });

        return [
            'processed' => $processed,
            'country_mapped' => $countryMapped,
            'region_only' => $regionOnly,
            'still_unmapped' => $stillUnmapped,
            'skip_cleared' => $skipCleared,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    protected function normalizedCountrySliceChanged(array $before, array $after): bool
    {
        return ($before['country_id'] ?? null) !== ($after['country_id'] ?? null)
            || ($before['region_id'] ?? null) !== ($after['region_id'] ?? null)
            || ($before['country_confidence'] ?? null) !== ($after['country_confidence'] ?? null)
            || ($before['standardization']['country'] ?? null) !== ($after['standardization']['country'] ?? null);
    }
}
