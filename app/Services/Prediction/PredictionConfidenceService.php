<?php

namespace App\Services\Prediction;

use App\Enums\PredictionFallbackLevel;
use App\Enums\TrendDirection;
use App\Models\PricingStatistic;

class PredictionConfidenceService
{
    /**
     * @return array{score: int, breakdown: array<string, mixed>}
     */
    public function calculate(
        PricingStatistic $statistic,
        PredictionFallbackLevel $fallbackLevel,
        ?float $requestedQuantity,
        int $outlierCount,
    ): array {
        $historicalPoints = $this->scoreHistoricalAwards((int) $statistic->award_count);
        $recencyPoints = $this->scoreRecency($statistic->last_award_date);
        $stabilityPoints = $this->scoreMarketStability($statistic);
        $variationPoints = $this->scorePriceVariation($statistic, $outlierCount);
        $geoPoints = $fallbackLevel === PredictionFallbackLevel::Country ? 10 : 0;
        $quantityPoints = ($requestedQuantity !== null && $requestedQuantity > 0) ? 5 : 0;
        $diversityPoints = $this->scoreSupplierDiversity((int) $statistic->distinct_winners_count);

        $items = [
            [
                'key' => 'historical_awards',
                'label' => 'Historical Data',
                'points' => $historicalPoints,
            ],
            [
                'key' => 'data_recency',
                'label' => 'Recent Market Data',
                'points' => $recencyPoints,
            ],
            [
                'key' => 'market_stability',
                'label' => 'Market Stability',
                'points' => $stabilityPoints,
            ],
            [
                'key' => 'price_variation',
                'label' => 'Price Variation',
                'points' => $variationPoints,
            ],
            [
                'key' => 'country_level_data',
                'label' => 'Country-Level Data',
                'points' => $geoPoints,
            ],
            [
                'key' => 'quantity_context',
                'label' => 'Quantity Context',
                'points' => $quantityPoints,
            ],
            [
                'key' => 'supplier_diversity',
                'label' => 'Supplier Diversity',
                'points' => $diversityPoints,
            ],
        ];

        $total = array_sum(array_column($items, 'points'));
        $score = min(100, $total);

        return [
            'score' => $score,
            'breakdown' => [
                'items' => $items,
                'total' => $score,
            ],
        ];
    }

    protected function scoreHistoricalAwards(int $awardCount): int
    {
        if ($awardCount <= 0) {
            return 0;
        }

        return min(30, $awardCount * 3);
    }

    protected function scoreRecency(mixed $lastAwardDate): int
    {
        if ($lastAwardDate === null) {
            return 0;
        }

        $monthsAgo = (int) $lastAwardDate->diffInMonths(now());

        return match (true) {
            $monthsAgo <= 6 => 15,
            $monthsAgo <= 12 => 12,
            $monthsAgo <= 24 => 8,
            $monthsAgo <= 36 => 4,
            default => 0,
        };
    }

    protected function scoreMarketStability(PricingStatistic $statistic): int
    {
        $trend = TrendDirection::tryFrom((string) $statistic->trend_direction) ?? TrendDirection::Unknown;

        if ($trend === TrendDirection::Stable) {
            return 15;
        }

        if ($trend === TrendDirection::Unknown || $statistic->trend_pct === null) {
            return 0;
        }

        $trendPct = abs((float) $statistic->trend_pct);

        return match (true) {
            $trendPct <= 3 => 12,
            $trendPct <= 7 => 10,
            default => 8,
        };
    }

    protected function scorePriceVariation(PricingStatistic $statistic, int $outlierCount): int
    {
        $avg = (float) ($statistic->avg_unit_price ?? 0);
        $stdDev = (float) ($statistic->price_std_dev ?? 0);

        if ($avg <= 0) {
            $cvScore = 0;
        } else {
            $coefficient = $stdDev / $avg;

            $cvScore = match (true) {
                $coefficient <= 0.10 => 15,
                $coefficient <= 0.15 => 12,
                $coefficient <= 0.25 => 8,
                $coefficient <= 0.40 => 4,
                default => 0,
            };
        }

        if ($outlierCount === 0) {
            return min(15, $cvScore + ($cvScore > 0 ? 3 : 0));
        }

        return max(0, $cvScore - min(10, $outlierCount * 3));
    }

    protected function scoreSupplierDiversity(int $distinctWinnersCount): int
    {
        if ($distinctWinnersCount <= 1) {
            return 0;
        }

        return min(5, ($distinctWinnersCount - 1) * 2);
    }
}
