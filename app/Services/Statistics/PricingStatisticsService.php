<?php

namespace App\Services\Statistics;

use App\Enums\PricingStatisticScope;
use App\Models\BidRecord;
use App\Models\Country;
use App\Models\Currency;
use App\Models\PricingStatistic;
use Illuminate\Support\Collection;

class PricingStatisticsService
{
    public const STATS_VERSION_INITIAL = 'v1';

    public function __construct(
        protected PricingAggregationService $aggregation,
    ) {}

    /**
     * @return array{created: bool, skipped: bool, statistic: ?PricingStatistic}
     */
    public function calculateForDrugCountry(
        int $standardizedDrugId,
        int $countryId,
        bool $persist = true,
    ): array {
        $records = $this->eligibleRecordsQuery()
            ->where('standardized_drug_id', $standardizedDrugId)
            ->where('country_id', $countryId)
            ->get();

        if ($records->isEmpty()) {
            return ['created' => false, 'skipped' => true, 'statistic' => null];
        }

        $attributes = $this->buildAttributes($records, PricingStatisticScope::DrugCountry, [
            'standardized_drug_id' => $standardizedDrugId,
            'country_id' => $countryId,
            'region_id' => null,
        ]);

        if (! $persist) {
            return [
                'created' => false,
                'skipped' => false,
                'statistic' => new PricingStatistic($attributes),
            ];
        }

        return $this->upsertStatistic($attributes, [
            'standardized_drug_id' => $standardizedDrugId,
            'country_id' => $countryId,
            'region_id' => null,
        ]);
    }

    /**
     * @return array{created: bool, skipped: bool, statistic: ?PricingStatistic}
     */
    public function calculateForDrugRegion(
        int $standardizedDrugId,
        int $regionId,
        bool $persist = true,
    ): array {
        $countryIds = Country::query()
            ->where('region_id', $regionId)
            ->pluck('id');

        $records = $this->eligibleRecordsQuery()
            ->where('standardized_drug_id', $standardizedDrugId)
            ->whereIn('country_id', $countryIds)
            ->get();

        if ($records->isEmpty()) {
            return ['created' => false, 'skipped' => true, 'statistic' => null];
        }

        $attributes = $this->buildAttributes($records, PricingStatisticScope::DrugRegion, [
            'standardized_drug_id' => $standardizedDrugId,
            'country_id' => null,
            'region_id' => $regionId,
        ]);

        if (! $persist) {
            return [
                'created' => false,
                'skipped' => false,
                'statistic' => new PricingStatistic($attributes),
            ];
        }

        return $this->upsertStatistic($attributes, [
            'standardized_drug_id' => $standardizedDrugId,
            'country_id' => null,
            'region_id' => $regionId,
        ]);
    }

    /**
     * @param  list<int>  $tenderIds
     * @return array{created: bool, skipped: bool, statistic: ?PricingStatistic}
     */
    public function calculateForDrugTenderGroup(
        int $standardizedDrugId,
        array $tenderIds,
        bool $persist = false,
    ): array {
        if ($tenderIds === []) {
            return ['created' => false, 'skipped' => true, 'statistic' => null];
        }

        $records = $this->eligibleRecordsQuery()
            ->where('standardized_drug_id', $standardizedDrugId)
            ->whereIn('tender_id', $tenderIds)
            ->get();

        if ($records->isEmpty()) {
            return ['created' => false, 'skipped' => true, 'statistic' => null];
        }

        $attributes = $this->buildAttributes($records, PricingStatisticScope::DrugTenderGroup, [
            'standardized_drug_id' => $standardizedDrugId,
            'country_id' => null,
            'region_id' => null,
        ]);

        return [
            'created' => false,
            'skipped' => false,
            'statistic' => new PricingStatistic($attributes),
        ];
    }

    /**
     * @return array{created: bool, skipped: bool, statistic: ?PricingStatistic}
     */
    public function calculateForDrugGlobal(int $standardizedDrugId, bool $persist = true): array
    {
        $records = $this->eligibleRecordsQuery()
            ->where('standardized_drug_id', $standardizedDrugId)
            ->get();

        if ($records->isEmpty()) {
            return ['created' => false, 'skipped' => true, 'statistic' => null];
        }

        $attributes = $this->buildAttributes($records, PricingStatisticScope::DrugGlobal, [
            'standardized_drug_id' => $standardizedDrugId,
            'country_id' => null,
            'region_id' => null,
        ]);

        if (! $persist) {
            return [
                'created' => false,
                'skipped' => false,
                'statistic' => new PricingStatistic($attributes),
            ];
        }

        return $this->upsertStatistic($attributes, [
            'standardized_drug_id' => $standardizedDrugId,
            'country_id' => null,
            'region_id' => null,
        ]);
    }

    /**
     * @return Collection<int, array{standardized_drug_id: int, country_id: int}>
     */
    public function discoverDrugCountryGroups(?int $drugId = null, ?int $countryId = null): Collection
    {
        return $this->eligibleRecordsQuery()
            ->when($drugId, fn ($q) => $q->where('standardized_drug_id', $drugId))
            ->when($countryId, fn ($q) => $q->where('country_id', $countryId))
            ->select('standardized_drug_id', 'country_id')
            ->distinct()
            ->orderBy('standardized_drug_id')
            ->orderBy('country_id')
            ->get()
            ->map(fn ($row) => [
                'standardized_drug_id' => (int) $row->standardized_drug_id,
                'country_id' => (int) $row->country_id,
            ]);
    }

    /**
     * @return Collection<int, array{standardized_drug_id: int, region_id: int}>
     */
    public function discoverDrugRegionGroups(?int $drugId = null): Collection
    {
        return $this->eligibleRecordsQuery()
            ->when($drugId, fn ($q) => $q->where('standardized_drug_id', $drugId))
            ->join('countries', 'countries.id', '=', 'bid_records.country_id')
            ->select('bid_records.standardized_drug_id', 'countries.region_id')
            ->distinct()
            ->orderBy('bid_records.standardized_drug_id')
            ->orderBy('countries.region_id')
            ->get()
            ->map(fn ($row) => [
                'standardized_drug_id' => (int) $row->standardized_drug_id,
                'region_id' => (int) $row->region_id,
            ]);
    }

    /**
     * @return Collection<int, int>
     */
    public function discoverDrugGlobalGroups(?int $drugId = null): Collection
    {
        return $this->eligibleRecordsQuery()
            ->when($drugId, fn ($q) => $q->where('standardized_drug_id', $drugId))
            ->distinct()
            ->orderBy('standardized_drug_id')
            ->pluck('standardized_drug_id')
            ->map(fn ($id) => (int) $id);
    }

    /**
     * @param  Collection<int, BidRecord>  $records
     * @param  array{standardized_drug_id: int, country_id: ?int, region_id: ?int}  $scopeKeys
     * @return array<string, mixed>
     */
    protected function buildAttributes(
        Collection $records,
        PricingStatisticScope $scope,
        array $scopeKeys,
    ): array {
        $prices = $records->map(fn (BidRecord $r) => (float) $r->price_usd)->all();
        $yearlyMedians = $this->aggregation->yearlyMedianPrices($records);
        $trend = $this->aggregation->trendFromYearlyMedians($yearlyMedians);
        $lastRecord = $this->aggregation->resolveLastAwardedRecord($records);

        return array_merge($scopeKeys, [
            'currency_id' => $this->resolveCurrencyId($records),
            'award_count' => $records->count(),
            'last_unit_price' => $lastRecord ? (float) $lastRecord->price_usd : null,
            'avg_unit_price' => $this->aggregation->average($prices),
            'weighted_avg_unit_price' => $this->aggregation->weightedAverageUnitPrice($records),
            'median_unit_price' => $this->aggregation->median($prices),
            'min_unit_price' => min($prices),
            'max_unit_price' => max($prices),
            'price_std_dev' => $this->aggregation->populationStandardDeviation($prices),
            'last_award_date' => $lastRecord
                ? $this->aggregation->awardDateFromRecord($lastRecord)
                : null,
            'trend_direction' => $trend['direction']->value,
            'trend_pct' => $trend['pct'],
            'top_winner_company_id' => $this->aggregation->resolveTopWinnerCompanyId($records),
            'distinct_winners_count' => $this->aggregation->distinctWinnersCount($records),
            'stats_version' => self::STATS_VERSION_INITIAL,
            'calculated_at' => now(),
            'metadata_scope' => $scope->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array{standardized_drug_id: int, country_id: ?int, region_id: ?int}  $keys
     * @return array{created: bool, skipped: bool, statistic: PricingStatistic}
     */
    protected function upsertStatistic(array $attributes, array $keys): array
    {
        unset($attributes['metadata_scope']);

        $existing = PricingStatistic::query()
            ->where('standardized_drug_id', $keys['standardized_drug_id'])
            ->when(
                $keys['country_id'] !== null,
                fn ($q) => $q->where('country_id', $keys['country_id'])->whereNull('region_id'),
                fn ($q) => $q->whereNull('country_id')
                    ->when(
                        $keys['region_id'] !== null,
                        fn ($inner) => $inner->where('region_id', $keys['region_id']),
                        fn ($inner) => $inner->whereNull('region_id'),
                    ),
            )
            ->first();

        if ($existing !== null) {
            $attributes['stats_version'] = $this->nextStatsVersion($existing->stats_version);
            $existing->update($attributes);

            return [
                'created' => false,
                'skipped' => false,
                'statistic' => $existing->fresh(),
            ];
        }

        $statistic = PricingStatistic::query()->create($attributes);

        return [
            'created' => true,
            'skipped' => false,
            'statistic' => $statistic,
        ];
    }

    public function nextStatsVersion(?string $current): string
    {
        if ($current === null || ! preg_match('/^v(\d+)$/i', $current, $matches)) {
            return self::STATS_VERSION_INITIAL;
        }

        return 'v'.((int) $matches[1] + 1);
    }

    /**
     * @param  Collection<int, BidRecord>  $records
     */
    /**
     * Pricing statistics are computed from price_usd; currency metadata is always USD.
     */
    protected function resolveCurrencyId(Collection $records): ?int
    {
        return \App\Support\RecommendationCurrency::usdCurrencyId()
            ?? Currency::query()->where('code', 'USD')->value('id');
    }

    protected function eligibleRecordsQuery()
    {
        return BidRecord::query()->analyticsEligible();
    }
}
