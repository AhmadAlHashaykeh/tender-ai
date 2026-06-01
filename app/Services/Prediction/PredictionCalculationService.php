<?php

namespace App\Services\Prediction;

use App\Enums\PredictionFallbackLevel;
use App\Enums\TrendDirection;
use App\Models\BidRecord;
use App\Models\OutlierFlag;
use App\Models\PricingStatistic;
use App\Models\Setting;
use App\Support\RecommendationCurrency;
use App\Services\Settings\SettingsService;
use App\Services\Statistics\OutlierDetectionService;

class PredictionCalculationService
{
    public const TREND_CAP_PCT = 7.0;

    public function __construct(
        protected PricingStatsResolver $statsResolver,
        protected PredictionConfidenceService $confidenceService,
        protected PredictionRiskService $riskService,
        protected SettingsService $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function calculate(
        int $standardizedDrugId,
        int $countryId,
        ?float $quantity = null,
        ?int $tenderId = null,
        float $discountPercentage = 0.0,
    ): array {
        $resolved = $this->statsResolver->resolve($standardizedDrugId, $countryId);

        if ($resolved['statistic'] === null) {
            return [
                'success' => false,
                'message' => 'No usable pricing statistics found for this drug and geography.',
                'fallback_level' => $resolved['fallback_level'],
            ];
        }

        /** @var PricingStatistic $statistic */
        $statistic = $resolved['statistic'];
        $fallbackLevel = $resolved['fallback_level'];

        $baseResult = $this->computeBasePrice($statistic);
        if ($baseResult['base_price'] === null) {
            return [
                'success' => false,
                'message' => 'Insufficient price data to compute a recommendation.',
                'fallback_level' => $fallbackLevel,
            ];
        }

        $trendAdjusted = $this->applyTrendAdjustment(
            $baseResult['base_price'],
            $statistic,
        );

        $marketCalculatedPrice = round($trendAdjusted['price'], 6);
        $discountPercentage = max(0.0, min(100.0, $discountPercentage));
        $finalRecommendedPrice = round(
            $marketCalculatedPrice * (1 - ($discountPercentage / 100)),
            6,
        );

        $outlierCount = $this->countOutliers($standardizedDrugId, $countryId);
        $competition = $this->resolveCompetition($statistic);
        $confidenceResult = $this->confidenceService->calculate(
            $statistic,
            $fallbackLevel,
            $quantity,
            $outlierCount,
        );
        $confidenceScore = $confidenceResult['score'];
        $riskResult = $this->riskService->calculate(
            $confidenceScore,
            $statistic,
            $fallbackLevel,
            $outlierCount,
        );
        $riskLevel = $riskResult['level'];

        $calculationDetails = [
            'formula' => 'weighted_avg*0.40 + median*0.30 + last*0.20 + avg*0.10',
            'components' => $baseResult['components'],
            'weights_used' => $baseResult['weights_used'],
            'base_price' => $baseResult['base_price'],
            'trend_adjustment' => $trendAdjusted['adjustment'],
            'trend_adjusted_price' => $trendAdjusted['price'],
            'market_calculated_price' => $marketCalculatedPrice,
            'discount_percentage' => $discountPercentage,
            'final_recommended_price' => $finalRecommendedPrice,
            'fallback_level' => $fallbackLevel->value,
            'market_data_scope' => $fallbackLevel->label(),
            'tender_id' => $tenderId,
            'confidence_breakdown' => $confidenceResult['breakdown'],
            'risk_breakdown' => $riskResult['breakdown'],
        ];

        return [
            'success' => true,
            'statistic' => $statistic,
            'fallback_level' => $fallbackLevel,
            'market_calculated_price' => $marketCalculatedPrice,
            'discount_percentage' => $discountPercentage,
            'final_recommended_price' => $finalRecommendedPrice,
            'backend_recommended_price' => $finalRecommendedPrice,
            'recommended_price' => $finalRecommendedPrice,
            'currency_id' => RecommendationCurrency::usdCurrencyId() ?? $statistic->currency_id,
            'stats_version' => $statistic->stats_version,
            'calculation_model_version' => Setting::getValue('prediction.calculation_model_version', 'v1.0'),
            'confidence_score' => $confidenceScore,
            'risk_level' => $riskLevel,
            'outlier_count' => $outlierCount,
            'competition' => $competition,
            'calculation_details' => $calculationDetails,
            'calculation_record' => [
                'last_winning_price' => $statistic->last_unit_price,
                'average_price' => $statistic->avg_unit_price,
                'weighted_average_price' => $statistic->weighted_avg_unit_price,
                'median_price' => $statistic->median_unit_price,
                'min_price' => $statistic->min_unit_price,
                'max_price' => $statistic->max_unit_price,
                'recommended_price' => $finalRecommendedPrice,
                'price_trend' => $statistic->trend_direction,
                'trend_pct' => $statistic->trend_pct,
                'competition_level' => $competition['level'],
                'competition_score' => $competition['score'],
                'outlier_count' => $outlierCount,
                'historical_award_count' => $statistic->award_count,
                'confidence_score' => $confidenceScore,
                'calculation_model_version' => Setting::getValue('prediction.calculation_model_version', 'v1.0'),
                'calculation_details' => $calculationDetails,
            ],
        ];
    }

    /**
     * @return array{base_price: ?float, components: array<string, ?float>, weights_used: array<string, float>}
     */
    protected function computeBasePrice(PricingStatistic $statistic): array
    {
        $components = [
            'weighted_avg' => $this->positiveOrNull($statistic->weighted_avg_unit_price),
            'median' => $this->positiveOrNull($statistic->median_unit_price),
            'last' => $this->positiveOrNull($statistic->last_unit_price),
            'avg' => $this->positiveOrNull($statistic->avg_unit_price),
        ];

        $defaultWeights = [
            'weighted_avg' => 0.40,
            'median' => 0.30,
            'last' => 0.20,
            'avg' => 0.10,
        ];

        $activeWeight = 0.0;
        $weightedSum = 0.0;
        $weightsUsed = [];

        foreach ($defaultWeights as $key => $weight) {
            if ($components[$key] !== null) {
                $activeWeight += $weight;
                $weightsUsed[$key] = $weight;
            }
        }

        if ($activeWeight <= 0) {
            return [
                'base_price' => null,
                'components' => $components,
                'weights_used' => [],
            ];
        }

        foreach ($weightsUsed as $key => $weight) {
            $normalized = $weight / $activeWeight;
            $weightsUsed[$key] = round($normalized, 6);
            $weightedSum += $components[$key] * $normalized;
        }

        return [
            'base_price' => round($weightedSum, 6),
            'components' => $components,
            'weights_used' => $weightsUsed,
        ];
    }

    /**
     * @return array{price: float, adjustment: array<string, mixed>}
     */
    protected function applyTrendAdjustment(float $basePrice, PricingStatistic $statistic): array
    {
        $trend = TrendDirection::tryFrom((string) $statistic->trend_direction) ?? TrendDirection::Unknown;
        $trendPct = $statistic->trend_pct !== null ? abs((float) $statistic->trend_pct) : 0.0;
        $trendCap = $this->settings->getFloat('prediction.trend_adjustment_cap', self::TREND_CAP_PCT) ?? self::TREND_CAP_PCT;
        $appliedPct = min($trendPct, $trendCap);

        $multiplier = 1.0;

        if ($trend === TrendDirection::Rising && $appliedPct > 0) {
            $multiplier = 1 + ($appliedPct / 100);
        } elseif ($trend === TrendDirection::Falling && $appliedPct > 0) {
            $multiplier = 1 - ($appliedPct / 100);
        }

        return [
            'price' => round($basePrice * $multiplier, 6),
            'adjustment' => [
                'direction' => $trend->value,
                'trend_pct' => $statistic->trend_pct,
                'applied_pct' => $trend === TrendDirection::Stable || $trend === TrendDirection::Unknown
                    ? 0
                    : $appliedPct,
                'multiplier' => $multiplier,
            ],
        ];
    }

    /**
     * @return array{level: string, score: float}
     */
    protected function resolveCompetition(PricingStatistic $statistic): array
    {
        $winners = (int) $statistic->distinct_winners_count;

        return match (true) {
            $winners >= 6 => ['level' => 'high', 'score' => 0.85],
            $winners >= 3 => ['level' => 'medium', 'score' => 0.55],
            default => ['level' => 'low', 'score' => 0.25],
        };
    }

    protected function countOutliers(int $standardizedDrugId, int $countryId): int
    {
        $bidIds = BidRecord::query()
            ->analyticsEligible()
            ->where('standardized_drug_id', $standardizedDrugId)
            ->where('country_id', $countryId)
            ->pluck('id');

        if ($bidIds->isEmpty()) {
            return 0;
        }

        return OutlierFlag::query()
            ->where('entity_type', 'bid_record')
            ->whereIn('entity_id', $bidIds)
            ->where('flag_type', OutlierDetectionService::FLAG_TYPE)
            ->where('is_resolved', false)
            ->count();
    }

    protected function positiveOrNull(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $float = (float) $value;

        return $float > 0 ? $float : null;
    }
}
