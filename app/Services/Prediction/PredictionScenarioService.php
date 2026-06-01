<?php

namespace App\Services\Prediction;

use App\Enums\PredictionRiskLevel;
use App\Enums\PredictionScenarioType;
use App\Enums\PredictionSource;
use App\Enums\TrendDirection;
use App\Models\PricingStatistic;
use App\Services\Settings\SettingsService;

class PredictionScenarioService
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function generate(
        float $finalRecommendedPrice,
        PredictionRiskLevel $riskLevel,
        int $confidenceScore,
        PricingStatistic $statistic,
        array $competition,
        float $userDiscountPercentage = 0.0,
    ): array {
        $trend = TrendDirection::tryFrom((string) $statistic->trend_direction) ?? TrendDirection::Unknown;
        $competitionLevel = $competition['level'] ?? 'low';

        $aggressiveDiscount = ($this->settings->getFloat('prediction.aggressive_discount_percent', 3) ?? 3) / 100;
        $conservativePremium = ($this->settings->getFloat('prediction.conservative_premium_percent', 3) ?? 3) / 100;

        $balanced = round($finalRecommendedPrice, 6);
        $aggressive = round($finalRecommendedPrice * (1 - $aggressiveDiscount), 6);
        $conservative = round($finalRecommendedPrice * (1 + $conservativePremium), 6);

        if ($trend === TrendDirection::Rising) {
            $conservative = round($finalRecommendedPrice * (1 + $conservativePremium + 0.02), 6);
        } elseif ($trend === TrendDirection::Falling) {
            $aggressive = round($finalRecommendedPrice * (1 - $aggressiveDiscount - 0.02), 6);
        }

        if ($competitionLevel === 'high') {
            $aggressive = round(min($aggressive, $finalRecommendedPrice * (1 - $aggressiveDiscount - 0.01)), 6);
        }

        return [
            $this->buildScenario(
                PredictionScenarioType::Aggressive,
                $aggressive,
                $riskLevel,
                $confidenceScore,
                $statistic,
                $competitionLevel,
                false,
                $userDiscountPercentage,
            ),
            $this->buildScenario(
                PredictionScenarioType::Balanced,
                $balanced,
                $riskLevel,
                $confidenceScore,
                $statistic,
                $competitionLevel,
                true,
                $userDiscountPercentage,
            ),
            $this->buildScenario(
                PredictionScenarioType::Conservative,
                $conservative,
                $riskLevel,
                $confidenceScore,
                $statistic,
                $competitionLevel,
                false,
                $userDiscountPercentage,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildScenario(
        PredictionScenarioType $type,
        float $price,
        PredictionRiskLevel $riskLevel,
        int $confidenceScore,
        PricingStatistic $statistic,
        string $competitionLevel,
        bool $isRecommended,
        float $userDiscountPercentage = 0.0,
    ): array {
        $position = $this->estimateCompetitivePosition($type, $confidenceScore, $statistic, $competitionLevel);

        return [
            'scenario_name' => $type->value,
            'recommended_price' => $price,
            'win_probability' => $position['score'],
            'risk_level' => $riskLevel->value,
            'is_recommended' => $isRecommended,
            'rationale' => $this->rationaleFor($type, $price, $statistic, $userDiscountPercentage),
            'source' => PredictionSource::BackendTemplate->value,
            'metadata' => [
                'competitive_position' => $position,
                'user_discount_percentage' => $userDiscountPercentage,
            ],
        ];
    }

    /**
     * @return array{score: float, breakdown: array<string, mixed>}
     */
    protected function estimateCompetitivePosition(
        PredictionScenarioType $type,
        int $confidenceScore,
        PricingStatistic $statistic,
        string $competitionLevel,
    ): array {
        $base = min(95, max(45, $confidenceScore));

        $strategyAdjustment = match ($type) {
            PredictionScenarioType::Aggressive => 8,
            PredictionScenarioType::Balanced => 4,
            PredictionScenarioType::Conservative => -10,
        };

        $score = match ($type) {
            PredictionScenarioType::Aggressive => min(98, $base + $strategyAdjustment),
            PredictionScenarioType::Balanced => min(95, $base + $strategyAdjustment),
            PredictionScenarioType::Conservative => max(40, $base + $strategyAdjustment),
        };

        $items = [
            [
                'key' => 'data_confidence',
                'label' => 'Data confidence baseline',
                'value' => $base,
            ],
            [
                'key' => 'pricing_strategy',
                'label' => match ($type) {
                    PredictionScenarioType::Aggressive => 'More competitive pricing strategy',
                    PredictionScenarioType::Balanced => 'Balanced pricing strategy',
                    PredictionScenarioType::Conservative => 'Conservative pricing strategy',
                },
                'value' => $strategyAdjustment >= 0 ? '+'.$strategyAdjustment : (string) $strategyAdjustment,
            ],
        ];

        if ($competitionLevel === 'high' && $type === PredictionScenarioType::Aggressive) {
            $items[] = [
                'key' => 'competition',
                'label' => 'High market competition considered',
                'value' => 'Stronger competitive positioning',
            ];
        }

        $trend = TrendDirection::tryFrom((string) $statistic->trend_direction) ?? TrendDirection::Unknown;
        if ($trend !== TrendDirection::Unknown && $trend !== TrendDirection::Stable) {
            $items[] = [
                'key' => 'market_trend',
                'label' => ucfirst($trend->value).' market trend considered',
                'value' => 'Reflected in scenario price',
            ];
        }

        return [
            'score' => $score,
            'breakdown' => [
                'items' => $items,
                'total' => $score,
                'disclaimer' => 'This is an estimated positioning indicator based on available market data, not a guaranteed win probability.',
            ],
        ];
    }

    protected function rationaleFor(
        PredictionScenarioType $type,
        float $price,
        PricingStatistic $statistic,
        float $userDiscountPercentage = 0.0,
    ): string {
        $awards = (int) $statistic->award_count;
        $trend = $statistic->trend_direction ?? 'unknown';
        $discountNote = $userDiscountPercentage > 0
            ? sprintf(' (includes your %.1f%% bid discount)', $userDiscountPercentage)
            : '';

        return match ($type) {
            PredictionScenarioType::Aggressive => sprintf(
                'Aggressive bid at %s per unit offers a lower price based on %d historical awards and a %s market trend%s.',
                number_format($price, 2),
                $awards,
                $trend,
                $discountNote,
            ),
            PredictionScenarioType::Balanced => sprintf(
                'Balanced bid at %s per unit matches your final discounted recommendation derived from weighted historical statistics (%d awards)%s.',
                number_format($price, 2),
                $awards,
                $discountNote,
            ),
            PredictionScenarioType::Conservative => sprintf(
                'Conservative bid at %s per unit adds margin buffer against price volatility and %s trend conditions%s.',
                number_format($price, 2),
                $trend,
                $discountNote,
            ),
        };
    }
}
