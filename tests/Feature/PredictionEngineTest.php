<?php

namespace Tests\Feature;

use App\Enums\PredictionFallbackLevel;
use App\Enums\PredictionRiskLevel;
use App\Enums\PredictionSource;
use App\Models\BidRecord;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\OutlierFlag;
use App\Models\Prediction;
use App\Models\PredictionCalculation;
use App\Models\PredictionContextSnapshot;
use App\Models\PredictionScenario;
use App\Models\PricingStatistic;
use App\Models\Setting;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Models\TenderItem;
use App\Models\User;
use App\Services\Prediction\PredictionCalculationService;
use App\Services\Prediction\PredictionConfidenceService;
use App\Services\Prediction\PredictionOrchestratorService;
use App\Services\Statistics\OutlierDetectionService;
use App\Services\Statistics\PricingStatisticsService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenderRecommendations;
use Tests\TestCase;

class PredictionEngineTest extends TestCase
{
    use CreatesTenderRecommendations;
    use RefreshDatabase;

    protected User $user;

    protected Country $country;

    protected StandardizedDrug $drug;

    protected Currency $currency;

    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RegionSeeder::class,
            CurrencySeeder::class,
            CountrySeeder::class,
            SettingSeeder::class,
        ]);

        $this->user = User::factory()->create();
        $this->country = Country::query()->where('name', 'Saudi Arabia')->firstOrFail();
        $this->currency = Currency::query()->where('code', 'USD')->firstOrFail();
        $this->drug = StandardizedDrug::query()->create([
            'code' => 'PRED-001',
            'inn' => 'Predict Drug',
            'display_name' => 'Predict Drug 500mg',
            'is_active' => true,
            'source' => 'test',
        ]);
        $this->company = Company::query()->create([
            'name' => 'Predict Co',
            'normalized_name' => 'predict co',
            'is_active' => true,
            'source' => 'test',
        ]);
        $this->testTender = $this->createTestTender();
    }

    public function test_prediction_uses_country_level_pricing_stats(): void
    {
        $this->seedPricingStatsForCountry();

        $result = app(PredictionCalculationService::class)->calculate(
            $this->drug->id,
            $this->country->id,
            10000,
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(PredictionFallbackLevel::Country, $result['fallback_level']);
    }

    public function test_fallback_to_region_when_country_stats_missing(): void
    {
        $regionId = $this->country->region_id;

        PricingStatistic::query()->create([
            'standardized_drug_id' => $this->drug->id,
            'region_id' => $regionId,
            'country_id' => null,
            'currency_id' => $this->currency->id,
            'award_count' => 6,
            'weighted_avg_unit_price' => 12,
            'median_unit_price' => 11,
            'last_unit_price' => 13,
            'avg_unit_price' => 12,
            'min_unit_price' => 10,
            'max_unit_price' => 14,
            'trend_direction' => 'stable',
            'stats_version' => 'v1',
            'calculated_at' => now(),
        ]);

        $result = app(PredictionCalculationService::class)->calculate(
            $this->drug->id,
            $this->country->id,
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(PredictionFallbackLevel::Region, $result['fallback_level']);
    }

    public function test_fallback_to_global_when_country_and_region_missing(): void
    {
        PricingStatistic::query()->create([
            'standardized_drug_id' => $this->drug->id,
            'country_id' => null,
            'region_id' => null,
            'currency_id' => $this->currency->id,
            'award_count' => 8,
            'weighted_avg_unit_price' => 9,
            'median_unit_price' => 8.5,
            'last_unit_price' => 9.5,
            'avg_unit_price' => 9,
            'min_unit_price' => 7,
            'max_unit_price' => 11,
            'trend_direction' => 'stable',
            'stats_version' => 'v1',
            'calculated_at' => now(),
        ]);

        $result = app(PredictionCalculationService::class)->calculate(
            $this->drug->id,
            $this->country->id,
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(PredictionFallbackLevel::Global, $result['fallback_level']);
    }

    public function test_formula_uses_weighted_components_correctly(): void
    {
        PricingStatistic::query()->create([
            'standardized_drug_id' => $this->drug->id,
            'country_id' => $this->country->id,
            'currency_id' => $this->currency->id,
            'award_count' => 10,
            'weighted_avg_unit_price' => 100,
            'median_unit_price' => 80,
            'last_unit_price' => 60,
            'avg_unit_price' => 40,
            'min_unit_price' => 30,
            'max_unit_price' => 120,
            'trend_direction' => 'stable',
            'stats_version' => 'v1',
            'calculated_at' => now(),
        ]);

        $result = app(PredictionCalculationService::class)->calculate(
            $this->drug->id,
            $this->country->id,
        );

        $expectedBase = (100 * 0.4) + (80 * 0.3) + (60 * 0.2) + (40 * 0.1);
        $this->assertEqualsWithDelta($expectedBase, $result['calculation_details']['base_price'], 0.0001);
    }

    public function test_trend_adjustment_capped_at_seven_percent(): void
    {
        PricingStatistic::query()->create([
            'standardized_drug_id' => $this->drug->id,
            'country_id' => $this->country->id,
            'currency_id' => $this->currency->id,
            'award_count' => 10,
            'weighted_avg_unit_price' => 100,
            'median_unit_price' => 100,
            'last_unit_price' => 100,
            'avg_unit_price' => 100,
            'min_unit_price' => 90,
            'max_unit_price' => 110,
            'trend_direction' => 'rising',
            'trend_pct' => 20,
            'stats_version' => 'v1',
            'calculated_at' => now(),
        ]);

        $result = app(PredictionCalculationService::class)->calculate(
            $this->drug->id,
            $this->country->id,
        );

        $this->assertEqualsWithDelta(7.0, $result['calculation_details']['trend_adjustment']['applied_pct'], 0.0001);
        $this->assertEqualsWithDelta(107.0, $result['calculation_details']['trend_adjusted_price'], 0.0001);
    }

    public function test_market_price_is_not_adjusted_by_quantity(): void
    {
        $this->seedPricingStatsForCountry();

        $small = app(PredictionCalculationService::class)->calculate(
            $this->drug->id,
            $this->country->id,
            100,
            null,
            0.0,
        );

        $large = app(PredictionCalculationService::class)->calculate(
            $this->drug->id,
            $this->country->id,
            50000,
            null,
            0.0,
        );

        $this->assertTrue($small['success']);
        $this->assertTrue($large['success']);
        $this->assertEqualsWithDelta(
            (float) $small['market_calculated_price'],
            (float) $large['market_calculated_price'],
            0.000001,
        );
    }

    public function test_confidence_score_changes_with_data_quality(): void
    {
        $rich = new PricingStatistic([
            'award_count' => 12,
            'last_award_date' => now()->subMonths(6),
            'trend_direction' => 'stable',
            'avg_unit_price' => 10,
            'price_std_dev' => 0.5,
            'distinct_winners_count' => 4,
        ]);

        $sparse = new PricingStatistic([
            'award_count' => 1,
            'last_award_date' => null,
            'trend_direction' => 'unknown',
            'avg_unit_price' => 10,
            'price_std_dev' => 5,
            'distinct_winners_count' => 1,
        ]);

        $service = app(PredictionConfidenceService::class);

        $high = $service->calculate($rich, PredictionFallbackLevel::Country, 1000, 0)['score'];
        $low = $service->calculate($sparse, PredictionFallbackLevel::Global, null, 5)['score'];

        $this->assertGreaterThan($low, $high);
    }

    public function test_confidence_breakdown_is_stored_in_calculation_details(): void
    {
        $this->seedPricingStatsForCountry();

        $prediction = app(PredictionOrchestratorService::class)->run($this->user, $this->recommendationPayload([
            'quantity' => 10000,
        ]));

        $calculation = PredictionCalculation::query()
            ->where('prediction_id', $prediction->id)
            ->firstOrFail();

        $breakdown = $calculation->calculation_details['confidence_breakdown'] ?? null;

        $this->assertNotNull($breakdown);
        $this->assertArrayHasKey('items', $breakdown);
        $this->assertArrayHasKey('total', $breakdown);
        $this->assertEquals($prediction->confidence_score, $breakdown['total']);
    }

    public function test_risk_level_high_on_low_confidence(): void
    {
        $this->seedPricingStatsForCountry(['award_count' => 2, 'price_std_dev' => 8]);

        $prediction = app(PredictionOrchestratorService::class)->run($this->user, $this->recommendationPayload());

        $this->assertContains($prediction->risk_level, [
            PredictionRiskLevel::Medium->value,
            PredictionRiskLevel::High->value,
        ]);
    }

    public function test_prediction_creates_calculation_scenarios_and_snapshot(): void
    {
        $this->seedPricingStatsForCountry();

        $prediction = app(PredictionOrchestratorService::class)->run($this->user, $this->recommendationPayload([
            'quantity' => 10000,
        ]));

        $this->assertEquals('completed', $prediction->status);
        $this->assertDatabaseHas('prediction_calculations', [
            'prediction_id' => $prediction->id,
        ]);
        $this->assertEquals(3, PredictionScenario::query()->where('prediction_id', $prediction->id)->count());
        $this->assertEquals(1, PredictionContextSnapshot::query()->where('prediction_id', $prediction->id)->count());
        $this->assertFalse($prediction->openai_called);
        $this->assertEquals(PredictionSource::BackendOnly->value, $prediction->source);
    }

    public function test_store_route_runs_backend_prediction(): void
    {
        $this->seedPricingStatsForCountry();

        $response = $this->actingAs($this->user)->post(route('ai.recommendations.store'), $this->recommendationPayload());

        $prediction = Prediction::query()->latest('id')->first();
        $response->assertRedirect(route('ai.recommendations.show', $prediction));
        $this->assertEquals('completed', $prediction->status);
    }

    public function test_no_openai_is_called_or_referenced(): void
    {
        $this->seedPricingStatsForCountry();

        $prediction = app(PredictionOrchestratorService::class)->run($this->user, $this->recommendationPayload([
            'recommendation_mode' => 'calculation',
        ]));

        $this->assertFalse($prediction->openai_called);
        $this->assertNull($prediction->ai_model);
        $this->assertNull($prediction->ai_response_raw);

        foreach (glob(app_path('Services/Prediction/*.php')) as $file) {
            $contents = strtolower(file_get_contents($file));
            $this->assertStringNotContainsString('openai\\', $contents, basename($file));
            $this->assertStringNotContainsString('openai::', $contents, basename($file));
        }
    }

    public function test_prediction_fails_gracefully_without_stats(): void
    {
        $prediction = app(PredictionOrchestratorService::class)->run($this->user, $this->recommendationPayload());

        $this->assertEquals('failed', $prediction->status);
        $this->assertEquals(0, PredictionCalculation::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function seedPricingStatsForCountry(array $overrides = []): PricingStatistic
    {
        foreach (range(1, 6) as $i) {
            $this->createBid([
                'price_usd' => 10 + $i,
                'award_year' => 2020 + $i,
                'quantity' => 1000 * $i,
            ]);
        }

        app(PricingStatisticsService::class)->calculateForDrugCountry(
            $this->drug->id,
            $this->country->id,
        );

        $stat = PricingStatistic::query()
            ->where('standardized_drug_id', $this->drug->id)
            ->where('country_id', $this->country->id)
            ->firstOrFail();

        if ($overrides !== []) {
            $stat->update($overrides);
        }

        return $stat->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createBid(array $overrides = []): BidRecord
    {
        $tender = Tender::query()->create([
            'tender_number' => 'T-'.uniqid(),
            'country_id' => $this->country->id,
            'year' => $overrides['award_year'] ?? 2024,
            'status' => 'active',
        ]);

        $item = TenderItem::query()->create([
            'tender_id' => $tender->id,
            'standardized_drug_id' => $this->drug->id,
        ]);

        return BidRecord::query()->create(array_merge([
            'tender_item_id' => $item->id,
            'tender_id' => $tender->id,
            'standardized_drug_id' => $this->drug->id,
            'country_id' => $this->country->id,
            'company_id' => $this->company->id,
            'currency_id' => $this->currency->id,
            'bid_status' => 'awarded',
            'is_winner' => true,
            'row_type' => 'winning_bid',
            'price_usd' => 10,
            'quantity' => 1000,
            'award_year' => 2024,
            'is_analytics_ready' => true,
            'excluded_from_stats' => false,
        ], $overrides));
    }
}
