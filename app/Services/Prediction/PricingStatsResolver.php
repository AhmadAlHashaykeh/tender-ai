<?php

namespace App\Services\Prediction;

use App\Enums\PredictionFallbackLevel;
use App\Models\Country;
use App\Models\PricingStatistic;

class PricingStatsResolver
{
    /**
     * @return array{statistic: ?PricingStatistic, fallback_level: PredictionFallbackLevel}
     */
    public function resolve(int $standardizedDrugId, int $countryId): array
    {
        $countryStat = PricingStatistic::query()
            ->where('standardized_drug_id', $standardizedDrugId)
            ->where('country_id', $countryId)
            ->whereNull('region_id')
            ->first();

        if ($countryStat !== null && $this->hasUsablePrices($countryStat)) {
            return [
                'statistic' => $countryStat,
                'fallback_level' => PredictionFallbackLevel::Country,
            ];
        }

        $regionId = Country::query()->whereKey($countryId)->value('region_id');

        if ($regionId !== null) {
            $regionStat = PricingStatistic::query()
                ->where('standardized_drug_id', $standardizedDrugId)
                ->where('region_id', $regionId)
                ->whereNull('country_id')
                ->first();

            if ($regionStat !== null && $this->hasUsablePrices($regionStat)) {
                return [
                    'statistic' => $regionStat,
                    'fallback_level' => PredictionFallbackLevel::Region,
                ];
            }
        }

        $globalStat = PricingStatistic::query()
            ->where('standardized_drug_id', $standardizedDrugId)
            ->whereNull('country_id')
            ->whereNull('region_id')
            ->first();

        if ($globalStat !== null && $this->hasUsablePrices($globalStat)) {
            return [
                'statistic' => $globalStat,
                'fallback_level' => PredictionFallbackLevel::Global,
            ];
        }

        return [
            'statistic' => null,
            'fallback_level' => PredictionFallbackLevel::None,
        ];
    }

    protected function hasUsablePrices(PricingStatistic $statistic): bool
    {
        $values = [
            $statistic->weighted_avg_unit_price,
            $statistic->median_unit_price,
            $statistic->last_unit_price,
            $statistic->avg_unit_price,
        ];

        foreach ($values as $value) {
            if ($value !== null && (float) $value > 0) {
                return true;
            }
        }

        return false;
    }
}
