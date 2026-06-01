<?php

namespace App\Services\Statistics;

use App\Models\BidRecord;
use App\Models\ImportBatch;
use Illuminate\Support\Collection;

class StatisticsRefreshService
{
    public function __construct(
        protected PricingStatisticsService $pricingStatistics,
        protected OutlierDetectionService $outlierDetection,
    ) {}

    /**
     * @return array{
     *     groups_processed: int,
     *     pricing_statistics_created: int,
     *     pricing_statistics_updated: int,
     *     outliers_flagged: int,
     *     skipped_groups: int,
     *     drug_country_groups: int,
     *     drug_region_groups: int,
     *     drug_global_groups: int
     * }
     */
    public function refreshAll(bool $persist = true, bool $includeFallbacks = true): array
    {
        return $this->refreshSubset(null, null, $persist, $includeFallbacks);
    }

    /**
     * Refresh pricing statistics for drug × country pairs materialized from an import batch.
     *
     * @return array<string, int|string|null>
     */
    public function refreshForImportBatch(ImportBatch $batch, bool $persist = true): array
    {
        $summary = $this->emptySummary();

        $pairs = BidRecord::query()
            ->analyticsEligible()
            ->where('import_batch_id', $batch->id)
            ->whereNotNull('standardized_drug_id')
            ->whereNotNull('country_id')
            ->select('standardized_drug_id', 'country_id')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $outcome = $this->pricingStatistics->calculateForDrugCountry(
                (int) $pair->standardized_drug_id,
                (int) $pair->country_id,
                $persist,
            );

            $this->accumulatePricingOutcome($summary, $outcome);
            $summary['groups_processed']++;

            if ($outcome['skipped']) {
                $summary['skipped_groups']++;
            }

            if ($persist) {
                $outlierResult = $this->outlierDetection->detectForDrugCountry(
                    (int) $pair->standardized_drug_id,
                    (int) $pair->country_id,
                    true,
                );
                $summary['outliers_flagged'] += $outlierResult['outliers_flagged'];
                $summary['skipped_groups'] += $outlierResult['skipped_groups'];
            }
        }

        return $summary;
    }

    /**
     * @return array{
     *     groups_processed: int,
     *     pricing_statistics_created: int,
     *     pricing_statistics_updated: int,
     *     outliers_flagged: int,
     *     skipped_groups: int,
     *     drug_country_groups: int,
     *     drug_region_groups: int,
     *     drug_global_groups: int
     * }
     */
    public function refreshSubset(
        ?int $drugId = null,
        ?int $countryId = null,
        bool $persist = true,
        bool $includeFallbacks = true,
    ): array {
        $summary = $this->emptySummary();

        $countryGroups = $this->pricingStatistics->discoverDrugCountryGroups($drugId, $countryId);
        $summary['drug_country_groups'] = $countryGroups->count();

        foreach ($countryGroups as $group) {
            $outcome = $this->pricingStatistics->calculateForDrugCountry(
                $group['standardized_drug_id'],
                $group['country_id'],
                $persist,
            );

            $this->accumulatePricingOutcome($summary, $outcome);
            $summary['groups_processed']++;

            if ($outcome['skipped']) {
                $summary['skipped_groups']++;
            }

            if ($persist) {
                $outlierResult = $this->outlierDetection->detectForDrugCountry(
                    $group['standardized_drug_id'],
                    $group['country_id'],
                    true,
                );
                $summary['outliers_flagged'] += $outlierResult['outliers_flagged'];
                $summary['skipped_groups'] += $outlierResult['skipped_groups'];
            }
        }

        if ($includeFallbacks && $countryId === null) {
            $regionGroups = $this->pricingStatistics->discoverDrugRegionGroups($drugId);
            $summary['drug_region_groups'] = $regionGroups->count();

            foreach ($regionGroups as $group) {
                $outcome = $this->pricingStatistics->calculateForDrugRegion(
                    $group['standardized_drug_id'],
                    $group['region_id'],
                    $persist,
                );

                $this->accumulatePricingOutcome($summary, $outcome);
                $summary['groups_processed']++;

                if ($outcome['skipped']) {
                    $summary['skipped_groups']++;
                }
            }

            $globalGroups = $this->pricingStatistics->discoverDrugGlobalGroups($drugId);
            $summary['drug_global_groups'] = $globalGroups->count();

            foreach ($globalGroups as $standardizedDrugId) {
                $outcome = $this->pricingStatistics->calculateForDrugGlobal($standardizedDrugId, $persist);

                $this->accumulatePricingOutcome($summary, $outcome);
                $summary['groups_processed']++;

                if ($outcome['skipped']) {
                    $summary['skipped_groups']++;
                }
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, int>  $summary
     * @param  array{created: bool, skipped: bool}  $outcome
     */
    protected function accumulatePricingOutcome(array &$summary, array $outcome): void
    {
        if ($outcome['skipped']) {
            return;
        }

        if ($outcome['created']) {
            $summary['pricing_statistics_created']++;
        } else {
            $summary['pricing_statistics_updated']++;
        }
    }

    /**
     * @return array{
     *     groups_processed: int,
     *     pricing_statistics_created: int,
     *     pricing_statistics_updated: int,
     *     outliers_flagged: int,
     *     skipped_groups: int,
     *     drug_country_groups: int,
     *     drug_region_groups: int,
     *     drug_global_groups: int
     * }
     */
    protected function emptySummary(): array
    {
        return [
            'groups_processed' => 0,
            'pricing_statistics_created' => 0,
            'pricing_statistics_updated' => 0,
            'outliers_flagged' => 0,
            'skipped_groups' => 0,
            'drug_country_groups' => 0,
            'drug_region_groups' => 0,
            'drug_global_groups' => 0,
        ];
    }
}
