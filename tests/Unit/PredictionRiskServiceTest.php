<?php

namespace Tests\Unit;

use App\Enums\PredictionFallbackLevel;
use App\Enums\PredictionRiskLevel;
use App\Models\PricingStatistic;
use App\Services\Prediction\PredictionRiskService;
use Tests\TestCase;

class PredictionRiskServiceTest extends TestCase
{
    protected PredictionRiskService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PredictionRiskService::class);
    }

    public function test_risk_breakdown_includes_business_friendly_factors(): void
    {
        $statistic = new PricingStatistic([
            'award_count' => 2,
            'avg_unit_price' => 10,
            'price_std_dev' => 4,
            'distinct_winners_count' => 1,
            'trend_direction' => 'rising',
        ]);

        $result = $this->service->calculate(
            35,
            $statistic,
            PredictionFallbackLevel::Global,
            2,
        );

        $this->assertSame(PredictionRiskLevel::High, $result['level']);
        $this->assertArrayHasKey('breakdown', $result);
        $this->assertNotEmpty($result['breakdown']['items']);

        $labels = array_column($result['breakdown']['items'], 'label');
        $this->assertTrue(
            collect($labels)->contains(fn ($label) => str_contains($label, 'confidence') || str_contains($label, 'Global')),
        );
    }

    public function test_stable_market_with_strong_data_can_reduce_risk_points(): void
    {
        $statistic = new PricingStatistic([
            'award_count' => 8,
            'avg_unit_price' => 10,
            'price_std_dev' => 1,
            'distinct_winners_count' => 4,
            'trend_direction' => 'stable',
        ]);

        $result = $this->service->calculate(
            80,
            $statistic,
            PredictionFallbackLevel::Country,
            0,
        );

        $this->assertSame(PredictionRiskLevel::Low, $result['level']);
        $keys = array_column($result['breakdown']['items'], 'key');
        $this->assertContains('stable_market', $keys);
    }
}
