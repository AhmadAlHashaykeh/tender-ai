<?php

namespace Tests\Feature;

use App\Models\BidRecord;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Prediction;
use App\Models\PredictionCalculation;
use App\Models\PricingStatistic;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Models\TenderItem;
use App\Models\User;
use App\Services\Prediction\PredictionCalculationService;
use App\Services\Prediction\PredictionOrchestratorService;
use App\Services\Statistics\PricingStatisticsService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenderRecommendations;
use Tests\TestCase;

class PredictionDiscountTest extends TestCase
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
            'code' => 'DISC-001',
            'inn' => 'Discount Drug',
            'display_name' => 'Discount Drug 500mg',
            'is_active' => true,
            'source' => 'test',
        ]);
        $this->company = Company::query()->create([
            'name' => 'Discount Co',
            'normalized_name' => 'discount co',
            'is_active' => true,
            'source' => 'test',
        ]);
        $this->testTender = $this->createTestTender();
        $this->seedPricingStatsForCountry();
    }

    public function test_discount_field_is_required_on_store(): void
    {
        $payload = $this->recommendationPayload();
        unset($payload['discount_percentage']);

        $response = $this->actingAs($this->user)->post(route('ai.recommendations.store'), $payload);

        $response->assertSessionHasErrors(['discount_percentage' => 'Please enter the bid discount percentage.']);
        $this->assertSame(0, Prediction::query()->count());
    }

    public function test_discount_must_be_between_zero_and_one_hundred(): void
    {
        $response = $this->actingAs($this->user)->post(route('ai.recommendations.store'), $this->recommendationPayload([
            'discount_percentage' => 101,
        ]));

        $response->assertSessionHasErrors('discount_percentage');
    }

    public function test_discount_calculation_accuracy_for_five_percent(): void
    {
        $result = app(PredictionCalculationService::class)->calculate(
            $this->drug->id,
            $this->country->id,
            10000,
            $this->testTender->id,
            5.0,
        );

        $this->assertTrue($result['success']);
        $market = (float) $result['market_calculated_price'];
        $expectedFinal = round($market * 0.95, 6);

        $this->assertEqualsWithDelta($expectedFinal, (float) $result['final_recommended_price'], 0.000001);
        $this->assertEqualsWithDelta(5.0, (float) $result['discount_percentage'], 0.001);
    }

    public function test_zero_percent_discount_leaves_price_unchanged(): void
    {
        $result = app(PredictionCalculationService::class)->calculate(
            $this->drug->id,
            $this->country->id,
            10000,
            $this->testTender->id,
            0.0,
        );

        $this->assertTrue($result['success']);
        $this->assertEqualsWithDelta(
            (float) $result['market_calculated_price'],
            (float) $result['final_recommended_price'],
            0.000001,
        );
    }

    public function test_one_hundred_percent_discount_yields_zero_final_price(): void
    {
        $result = app(PredictionCalculationService::class)->calculate(
            $this->drug->id,
            $this->country->id,
            10000,
            $this->testTender->id,
            100.0,
        );

        $this->assertTrue($result['success']);
        $this->assertEqualsWithDelta(0.0, (float) $result['final_recommended_price'], 0.000001);
    }

    public function test_orchestrator_stores_discount_and_price_fields(): void
    {
        $prediction = app(PredictionOrchestratorService::class)->run($this->user, $this->recommendationPayload([
            'discount_percentage' => 8,
        ]));

        $this->assertEquals('completed', $prediction->status);
        $this->assertEqualsWithDelta(8.0, (float) $prediction->discount_percentage, 0.001);
        $this->assertNotNull($prediction->market_calculated_price);
        $this->assertNotNull($prediction->final_recommended_price);
        $this->assertEqualsWithDelta(
            (float) $prediction->market_calculated_price * 0.92,
            (float) $prediction->final_recommended_price,
            0.0001,
        );
    }

    public function test_result_page_displays_market_discount_and_final_price(): void
    {
        $response = $this->actingAs($this->user)->post(route('ai.recommendations.store'), $this->recommendationPayload([
            'discount_percentage' => 5,
        ]));

        $prediction = Prediction::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('ai.recommendations.show', $prediction));

        $show = $this->actingAs($this->user)->get(route('ai.recommendations.show', $prediction));

        $show->assertOk();
        $show->assertSee('Calculated Market Price');
        $show->assertSee('User Discount');
        $show->assertSee('Final Recommended Bid Price');
        $show->assertSee('5.00%');
        $show->assertSee(number_format((float) $prediction->market_calculated_price, 2));
        $show->assertSee(number_format((float) $prediction->final_recommended_price, 2));
    }

    public function test_old_prediction_without_discount_shows_compatibility_message(): void
    {
        $prediction = Prediction::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->user->id,
            'standardized_drug_id' => $this->drug->id,
            'quantity' => 1000,
            'recommended_price' => 12.5,
            'backend_recommended_price' => 12.5,
            'currency_id' => $this->currency->id,
            'win_probability' => 80,
            'risk_level' => 'medium',
            'status' => 'completed',
            'confidence_score' => 75,
            'source' => 'backend_only',
            'recommendation_mode' => 'calculation',
            'openai_called' => false,
            'rationale' => 'Legacy prediction.',
            'completed_at' => now(),
        ]);

        PredictionCalculation::query()->create([
            'prediction_id' => $prediction->id,
            'weighted_average_price' => 12,
            'median_price' => 11,
            'last_winning_price' => 13,
            'average_price' => 12,
            'recommended_price' => 12.5,
            'historical_award_count' => 5,
            'outlier_count' => 0,
            'price_trend' => 'stable',
        ]);

        $response = $this->actingAs($this->user)->get(route('ai.recommendations.show', $prediction));

        $response->assertOk();
        $response->assertSee('No manual discount was applied.');
        $response->assertSee('12.50');
    }

    public function test_quantity_does_not_change_market_calculated_price(): void
    {
        $smallQty = app(PredictionCalculationService::class)->calculate(
            $this->drug->id,
            $this->country->id,
            100,
            $this->testTender->id,
            0.0,
        );

        $largeQty = app(PredictionCalculationService::class)->calculate(
            $this->drug->id,
            $this->country->id,
            50000,
            $this->testTender->id,
            0.0,
        );

        $this->assertEqualsWithDelta(
            (float) $smallQty['market_calculated_price'],
            (float) $largeQty['market_calculated_price'],
            0.000001,
        );
    }

    protected function seedPricingStatsForCountry(): PricingStatistic
    {
        foreach (range(1, 6) as $i) {
            $tender = Tender::query()->create([
                'tender_number' => 'T-DISC-'.$i,
                'country_id' => $this->country->id,
                'year' => 2020 + $i,
                'status' => 'active',
            ]);
            $item = TenderItem::query()->create([
                'tender_id' => $tender->id,
                'standardized_drug_id' => $this->drug->id,
            ]);
            BidRecord::query()->create([
                'tender_item_id' => $item->id,
                'tender_id' => $tender->id,
                'standardized_drug_id' => $this->drug->id,
                'country_id' => $this->country->id,
                'company_id' => $this->company->id,
                'currency_id' => $this->currency->id,
                'bid_status' => 'awarded',
                'is_winner' => true,
                'row_type' => 'winning_bid',
                'price_usd' => 10 + $i,
                'quantity' => 1000 * $i,
                'award_year' => 2020 + $i,
                'is_analytics_ready' => true,
                'excluded_from_stats' => false,
            ]);
        }

        app(PricingStatisticsService::class)->calculateForDrugCountry(
            $this->drug->id,
            $this->country->id,
        );

        return PricingStatistic::query()
            ->where('standardized_drug_id', $this->drug->id)
            ->where('country_id', $this->country->id)
            ->firstOrFail();
    }
}
