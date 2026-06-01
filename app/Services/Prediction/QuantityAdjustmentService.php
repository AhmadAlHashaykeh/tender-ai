<?php

namespace App\Services\Prediction;

use App\Models\BidRecord;
use App\Services\Settings\SettingsService;
use App\Services\Statistics\PricingAggregationService;
use Illuminate\Support\Collection;

class QuantityAdjustmentService
{
    public function __construct(
        protected PricingAggregationService $aggregation,
        protected SettingsService $settings,
    ) {}

    /**
     * @return array{factor: float, median_historical_quantity: ?float, comparison: string}
     */
    public function calculate(
        ?float $requestedQuantity,
        int $standardizedDrugId,
        int $countryId,
    ): array {
        if ($requestedQuantity === null || $requestedQuantity <= 0) {
            return [
                'factor' => 1.0,
                'median_historical_quantity' => null,
                'comparison' => 'quantity_not_provided',
            ];
        }

        $quantities = $this->historicalQuantities($standardizedDrugId, $countryId);

        if ($quantities->isEmpty()) {
            return [
                'factor' => 1.0,
                'median_historical_quantity' => null,
                'comparison' => 'no_historical_quantities',
            ];
        }

        $median = $this->aggregation->median($quantities->all());

        if ($median === null || $median <= 0) {
            return [
                'factor' => 1.0,
                'median_historical_quantity' => null,
                'comparison' => 'invalid_median',
            ];
        }

        $factor = 1.0;
        $comparison = 'within_range';

        $largeMultiplier = $this->settings->getFloat('prediction.large_quantity_multiplier', 2) ?? 2;
        $smallMultiplier = $this->settings->getFloat('prediction.small_quantity_multiplier', 0.5) ?? 0.5;
        $largeDiscount = ($this->settings->getFloat('prediction.large_quantity_discount_percent', 3) ?? 3) / 100;
        $smallPremium = ($this->settings->getFloat('prediction.small_quantity_premium_percent', 3) ?? 3) / 100;

        if ($requestedQuantity >= ($median * $largeMultiplier)) {
            $factor = 1 - $largeDiscount;
            $comparison = 'much_larger_than_median';
        } elseif ($requestedQuantity <= ($median * $smallMultiplier)) {
            $factor = 1 + $smallPremium;
            $comparison = 'much_smaller_than_median';
        }

        return [
            'factor' => $factor,
            'median_historical_quantity' => $median,
            'comparison' => $comparison,
        ];
    }

    /**
     * @return Collection<int, float>
     */
    protected function historicalQuantities(int $standardizedDrugId, int $countryId): Collection
    {
        return BidRecord::query()
            ->analyticsEligible()
            ->where('standardized_drug_id', $standardizedDrugId)
            ->where('country_id', $countryId)
            ->whereNotNull('quantity')
            ->where('quantity', '>', 0)
            ->pluck('quantity')
            ->map(fn ($q) => (float) $q);
    }
}
