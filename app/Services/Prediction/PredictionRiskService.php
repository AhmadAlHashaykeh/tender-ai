<?php

namespace App\Services\Prediction;

use App\Enums\PredictionFallbackLevel;
use App\Enums\PredictionRiskLevel;
use App\Enums\TrendDirection;
use App\Models\PricingStatistic;

class PredictionRiskService
{
    /**
     * @return array{level: PredictionRiskLevel, breakdown: array<string, mixed>}
     */
    public function calculate(
        int $confidenceScore,
        PricingStatistic $statistic,
        PredictionFallbackLevel $fallbackLevel,
        int $outlierCount,
    ): array {
        $items = [];
        $riskPoints = 0;

        if ($confidenceScore < 40) {
            $riskPoints += 3;
            $items[] = [
                'key' => 'low_confidence',
                'label' => 'Limited historical data confidence',
                'points' => 3,
            ];
        } elseif ($confidenceScore < 60) {
            $riskPoints += 2;
            $items[] = [
                'key' => 'moderate_confidence',
                'label' => 'Moderate data confidence',
                'points' => 2,
            ];
        } elseif ($confidenceScore < 75) {
            $riskPoints += 1;
            $items[] = [
                'key' => 'acceptable_confidence',
                'label' => 'Acceptable but not strong data confidence',
                'points' => 1,
            ];
        }

        if (! $this->isLowVariance($statistic)) {
            $riskPoints += 2;
            $items[] = [
                'key' => 'high_variation',
                'label' => 'High price variation in historical awards',
                'points' => 2,
            ];
        }

        $awardCount = (int) $statistic->award_count;
        if ($awardCount < 3) {
            $riskPoints += 2;
            $items[] = [
                'key' => 'very_limited_awards',
                'label' => 'Very limited historical awards ('.$awardCount.')',
                'points' => 2,
            ];
        } elseif ($awardCount < 5) {
            $riskPoints += 1;
            $items[] = [
                'key' => 'limited_awards',
                'label' => 'Limited historical awards ('.$awardCount.')',
                'points' => 1,
            ];
        }

        if ($outlierCount >= 3) {
            $riskPoints += 3;
            $items[] = [
                'key' => 'many_outliers',
                'label' => 'Multiple outlier prices flagged ('.$outlierCount.')',
                'points' => 3,
            ];
        } elseif ($outlierCount >= 1) {
            $riskPoints += 1;
            $items[] = [
                'key' => 'some_outliers',
                'label' => 'Outlier prices flagged ('.$outlierCount.')',
                'points' => 1,
            ];
        }

        if ($fallbackLevel === PredictionFallbackLevel::Region) {
            $riskPoints += 2;
            $items[] = [
                'key' => 'regional_data',
                'label' => 'Regional data used instead of country-level statistics',
                'points' => 2,
            ];
        } elseif ($fallbackLevel === PredictionFallbackLevel::Global) {
            $riskPoints += 2;
            $items[] = [
                'key' => 'global_data',
                'label' => 'Global market data used instead of country-level statistics',
                'points' => 2,
            ];
        }

        $winners = (int) $statistic->distinct_winners_count;
        if ($winners <= 1) {
            $riskPoints += 1;
            $items[] = [
                'key' => 'low_supplier_diversity',
                'label' => 'Low supplier diversity in historical awards',
                'points' => 1,
            ];
        }

        $trend = TrendDirection::tryFrom((string) $statistic->trend_direction) ?? TrendDirection::Unknown;
        if ($trend === TrendDirection::Stable && $awardCount >= 5 && $confidenceScore >= 70) {
            $riskPoints = max(0, $riskPoints - 1);
            $items[] = [
                'key' => 'stable_market',
                'label' => 'Stable market with sufficient historical data',
                'points' => -1,
            ];
        }

        $level = match (true) {
            $riskPoints >= 5 => PredictionRiskLevel::High,
            $riskPoints >= 2 => PredictionRiskLevel::Medium,
            default => PredictionRiskLevel::Low,
        };

        return [
            'level' => $level,
            'breakdown' => [
                'items' => $items,
                'total_points' => $riskPoints,
                'level' => $level->value,
            ],
        ];
    }

    protected function isLowVariance(PricingStatistic $statistic): bool
    {
        $avg = (float) ($statistic->avg_unit_price ?? 0);
        $stdDev = (float) ($statistic->price_std_dev ?? 0);

        if ($avg <= 0) {
            return false;
        }

        return ($stdDev / $avg) <= 0.15;
    }
}
