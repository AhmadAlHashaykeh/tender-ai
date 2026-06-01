<?php

namespace Tests\Unit;

use App\Enums\PredictionFallbackLevel;
use App\Models\PricingStatistic;
use App\Services\Prediction\PredictionConfidenceService;
use Tests\TestCase;

class PredictionConfidenceServiceTest extends TestCase
{
    protected PredictionConfidenceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PredictionConfidenceService::class);
    }

    public function test_recency_awards_points_for_recent_last_award_date(): void
    {
        $statistic = $this->makeStatistic([
            'award_count' => 0,
            'last_award_date' => now()->subMonths(3),
        ]);

        $result = $this->service->calculate($statistic, PredictionFallbackLevel::Global, null, 0);

        $this->assertSame(15, $this->breakdownPoints($result, 'data_recency'));
    }

    public function test_recency_awards_no_points_for_old_last_award_date(): void
    {
        $statistic = $this->makeStatistic([
            'award_count' => 0,
            'last_award_date' => now()->subYears(5),
        ]);

        $result = $this->service->calculate($statistic, PredictionFallbackLevel::Global, null, 0);

        $this->assertSame(0, $this->breakdownPoints($result, 'data_recency'));
    }

    public function test_recency_awards_no_points_for_null_last_award_date(): void
    {
        $statistic = $this->makeStatistic([
            'award_count' => 0,
            'last_award_date' => null,
        ]);

        $result = $this->service->calculate($statistic, PredictionFallbackLevel::Global, null, 0);

        $this->assertSame(0, $this->breakdownPoints($result, 'data_recency'));
    }

    public function test_confidence_score_increases_with_award_count(): void
    {
        $fewAwards = $this->makeStatistic(['award_count' => 2]);
        $manyAwards = $this->makeStatistic(['award_count' => 12]);

        $few = $this->service->calculate($fewAwards, PredictionFallbackLevel::Country, 1000, 0);
        $many = $this->service->calculate($manyAwards, PredictionFallbackLevel::Country, 1000, 0);

        $this->assertGreaterThan($few['score'], $many['score']);
        $this->assertSame(6, $this->breakdownPoints($few, 'historical_awards'));
        $this->assertSame(30, $this->breakdownPoints($many, 'historical_awards'));
    }

    public function test_confidence_score_higher_for_recent_data_than_old_data(): void
    {
        $base = [
            'award_count' => 8,
            'trend_direction' => 'stable',
            'avg_unit_price' => 10,
            'price_std_dev' => 1,
            'distinct_winners_count' => 4,
        ];

        $recent = $this->makeStatistic(array_merge($base, [
            'last_award_date' => now()->subMonths(4),
        ]));
        $old = $this->makeStatistic(array_merge($base, [
            'last_award_date' => now()->subYears(4),
        ]));

        $recentResult = $this->service->calculate($recent, PredictionFallbackLevel::Country, 500, 0);
        $oldResult = $this->service->calculate($old, PredictionFallbackLevel::Country, 500, 0);

        $this->assertGreaterThan($oldResult['score'], $recentResult['score']);
    }

    public function test_confidence_breakdown_contains_all_business_factors(): void
    {
        $statistic = $this->makeStatistic([
            'award_count' => 7,
            'last_award_date' => now()->subMonths(2),
            'trend_direction' => 'stable',
            'avg_unit_price' => 10,
            'price_std_dev' => 0.8,
            'distinct_winners_count' => 3,
        ]);

        $result = $this->service->calculate($statistic, PredictionFallbackLevel::Country, 1000, 0);

        $this->assertArrayHasKey('breakdown', $result);
        $this->assertArrayHasKey('items', $result['breakdown']);
        $this->assertSame($result['score'], $result['breakdown']['total']);

        $keys = array_column($result['breakdown']['items'], 'key');
        $this->assertEquals([
            'historical_awards',
            'data_recency',
            'market_stability',
            'price_variation',
            'country_level_data',
            'quantity_context',
            'supplier_diversity',
        ], $keys);
    }

    public function test_scores_are_more_granular_than_old_bucket_values(): void
    {
        $profiles = [
            ['award_count' => 3, 'last_award_date' => now()->subMonths(5), 'price_std_dev' => 0.5],
            ['award_count' => 7, 'last_award_date' => now()->subMonths(10), 'price_std_dev' => 1.2],
            ['award_count' => 11, 'last_award_date' => now()->subMonths(18), 'price_std_dev' => 2.0],
            ['award_count' => 15, 'last_award_date' => now()->subMonths(30), 'price_std_dev' => 3.5],
        ];

        $scores = [];
        foreach ($profiles as $profile) {
            $statistic = $this->makeStatistic(array_merge([
                'trend_direction' => 'stable',
                'avg_unit_price' => 10,
                'distinct_winners_count' => 4,
            ], $profile));

            $scores[] = $this->service->calculate(
                $statistic,
                PredictionFallbackLevel::Country,
                1000,
                0,
            )['score'];
        }

        $this->assertGreaterThan(1, count(array_unique($scores)));
        $this->assertNotContains(55, $scores);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeStatistic(array $overrides = []): PricingStatistic
    {
        return new PricingStatistic(array_merge([
            'award_count' => 5,
            'last_award_date' => now()->subMonths(6),
            'trend_direction' => 'stable',
            'trend_pct' => null,
            'avg_unit_price' => 10,
            'price_std_dev' => 1,
            'distinct_winners_count' => 3,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function breakdownPoints(array $result, string $key): int
    {
        foreach ($result['breakdown']['items'] as $item) {
            if ($item['key'] === $key) {
                return (int) $item['points'];
            }
        }

        $this->fail("Breakdown key [{$key}] not found.");

        return 0;
    }
}
