<?php

namespace Tests\Feature;

use App\Models\BidRecord;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Support\RecommendationCurrency;
use App\Models\Prediction;
use App\Models\PredictionCalculation;
use App\Models\PredictionContextSnapshot;
use App\Models\PredictionScenario;
use App\Models\PricingStatistic;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Models\TenderItem;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Services\Statistics\PricingStatisticsService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\CreatesTenderRecommendations;
use Tests\TestCase;

class PredictionUiTest extends TestCase
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
            'code' => 'UI-001',
            'inn' => 'UI Drug',
            'display_name' => 'UI Test Drug',
            'is_active' => true,
            'source' => 'test',
        ]);
        $this->company = Company::query()->create([
            'name' => 'UI Co',
            'normalized_name' => 'ui co',
            'is_active' => true,
            'source' => 'test',
        ]);
        $this->testTender = $this->createTestTender();
    }

    public function test_create_page_loads_with_drugs_and_countries(): void
    {
        $response = $this->actingAs($this->user)->get(route('ai.recommendations.create'));

        $response->assertOk();
        $response->assertSee('UI Test Drug');
        $response->assertSee('Saudi Arabia');
        $response->assertSee('Select tender...');
        $response->assertSee('Bid Discount Percentage');
        $response->assertSee('Market geography (from tender)');
    }

    public function test_create_page_shows_readiness_checklist(): void
    {
        $response = $this->actingAs($this->user)->get(route('ai.recommendations.create'));

        $response->assertOk();
        $response->assertSee('Data Readiness');
        $response->assertSee('Products');
        $response->assertSee('Market statistics');
        $response->assertSee('AI insights');
        $response->assertDontSee('Analysis Method');
        $response->assertDontSee('AI-Assisted Analysis');
    }

    public function test_create_page_shows_empty_state_when_no_drugs(): void
    {
        StandardizedDrug::query()->delete();

        $response = $this->actingAs($this->user)->get(route('ai.recommendations.create'));

        $response->assertOk();
        $response->assertSee('No products available yet');
    }

    public function test_create_page_warns_when_no_pricing_statistics(): void
    {
        $response = $this->actingAs($this->user)->get(route('ai.recommendations.create'));

        $response->assertOk();
        $response->assertSee('No market statistics yet');
    }

    public function test_form_validation_requires_tender(): void
    {
        $response = $this->actingAs($this->user)->post(route('ai.recommendations.store'), [
            'standardized_drug_id' => $this->drug->id,
            'country_id' => $this->country->id,
            'quantity' => 1000,
            'quantity_unit' => 'units',
            'discount_percentage' => 0,
        ]);

        $response->assertSessionHasErrors('tender_id');
        $this->assertSame(0, Prediction::query()->count());
    }

    public function test_form_validation_shows_business_friendly_quantity_message(): void
    {
        $response = $this->actingAs($this->user)->post(route('ai.recommendations.store'), $this->recommendationPayload([
            'quantity' => 0,
        ]));

        $response->assertSessionHasErrors(['quantity' => 'Tender quantity must be greater than zero.']);
    }

    public function test_country_is_derived_from_tender_on_store(): void
    {
        $this->seedStats();

        $response = $this->actingAs($this->user)->post(route('ai.recommendations.store'), [
            'tender_id' => $this->testTender->id,
            'standardized_drug_id' => $this->drug->id,
            'quantity' => 5000,
            'quantity_unit' => 'units',
            'discount_percentage' => 0,
        ]);

        $prediction = Prediction::query()->with('tender')->first();
        $response->assertRedirect(route('ai.recommendations.show', $prediction));
        $this->assertEquals($this->testTender->id, $prediction->tender_id);
        $this->assertEquals($this->country->id, $prediction->tender->country_id);
    }

    public function test_form_validation_catches_missing_quantity(): void
    {
        $payload = $this->recommendationPayload();
        unset($payload['quantity']);

        $response = $this->actingAs($this->user)->post(route('ai.recommendations.store'), $payload);

        $response->assertSessionHasErrors(['quantity' => 'Please enter the required tender quantity.']);
        $this->assertSame(0, Prediction::query()->count());
    }

    public function test_form_validation_catches_invalid_quantity(): void
    {
        $response = $this->actingAs($this->user)->post(route('ai.recommendations.store'), $this->recommendationPayload([
            'quantity' => 0,
        ]));

        $response->assertSessionHasErrors('quantity');
        $this->assertSame(0, Prediction::query()->count());
    }

    public function test_store_creates_backend_prediction_when_stats_exist(): void
    {
        $this->seedStats();

        $response = $this->actingAs($this->user)->post(route('ai.recommendations.store'), $this->recommendationPayload([
            'quantity_unit' => 'tablets',
        ]));

        $prediction = Prediction::query()->first();
        $this->assertNotNull($prediction);
        $response->assertRedirect(route('ai.recommendations.show', $prediction));
        $this->assertEquals('completed', $prediction->status);
        $this->assertEquals('backend_only', $prediction->source);
        $this->assertFalse($prediction->openai_called);
        $this->assertEquals(1, PredictionCalculation::query()->where('prediction_id', $prediction->id)->count());
        $this->assertEquals(3, PredictionScenario::query()->where('prediction_id', $prediction->id)->count());
        $this->assertEquals(1, PredictionContextSnapshot::query()->where('prediction_id', $prediction->id)->count());
    }

    public function test_store_handles_missing_stats_gracefully(): void
    {
        $response = $this->actingAs($this->user)->post(route('ai.recommendations.store'), $this->recommendationPayload([
            'quantity' => 1000,
        ]));

        $response->assertRedirect(route('ai.recommendations.create'));
        $response->assertSessionHas('error');
        $this->assertEquals('failed', Prediction::query()->first()?->status);
    }

    public function test_store_creates_ai_insights_when_enabled(): void
    {
        $this->enableNarratives();
        $this->fakeOpenAi($this->sampleInsights());
        $this->seedStats();

        $response = $this->actingAs($this->user)->post(route('ai.recommendations.store'), $this->recommendationPayload([
            'quantity_unit' => 'tablets',
        ]));

        $prediction = Prediction::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('ai.recommendations.show', $prediction));
        $this->assertEquals('completed', $prediction->status);
        $this->assertSame('backend_only', $prediction->source);
        $this->assertTrue($prediction->openai_called);
        $this->assertSame('success', $prediction->ai_response_raw['insights_status'] ?? null);
        $this->assertDatabaseHas('ai_usage_logs', [
            'prediction_id' => $prediction->id,
            'feature' => 'prediction_narrative',
            'status' => 'success',
        ]);
    }

    public function test_ai_failure_does_not_break_prediction_store(): void
    {
        $this->enableNarratives();
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'rate limited', 'code' => 'rate_limit_exceeded']], 429)]);
        $this->seedStats();

        $response = $this->actingAs($this->user)->post(route('ai.recommendations.store'), $this->recommendationPayload());

        $prediction = Prediction::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('ai.recommendations.show', $prediction));
        $this->assertEquals('completed', $prediction->status);
        $this->assertNull($prediction->ai_response_raw['insights'] ?? null);
        $this->assertTrue($prediction->openai_called);
        $this->assertSame('unavailable', $prediction->ai_response_raw['insights_status'] ?? null);
    }

    public function test_no_openai_call_when_narrative_is_disabled(): void
    {
        Http::fake();
        $this->seedStats();

        $this->actingAs($this->user)->post(route('ai.recommendations.store'), $this->recommendationPayload())->assertRedirect();

        Http::assertNothingSent();
        $prediction = Prediction::query()->latest('id')->firstOrFail();
        $this->assertFalse($prediction->openai_called);
        $this->assertSame('skipped', $prediction->ai_response_raw['insights_status'] ?? null);
    }

    public function test_show_page_displays_prices_in_usd_even_when_prediction_currency_is_local(): void
    {
        $sar = Currency::query()->where('code', 'SAR')->firstOrFail();
        $prediction = $this->createCompletedPrediction([
            'currency_id' => $sar->id,
            'recommended_price' => 12.5,
            'backend_recommended_price' => 12.5,
            'market_calculated_price' => 13.0,
            'final_recommended_price' => 12.5,
        ]);

        $response = $this->actingAs($this->user)->get(route('ai.recommendations.show', $prediction));

        $response->assertOk();
        $response->assertSee(RecommendationCurrency::format(13.0), false);
        $response->assertSee(RecommendationCurrency::format(12.5), false);
        $response->assertSee('stored and calculated in', false);
        $response->assertSee('USD', false);
        $response->assertDontSee('SR12', false);
        $response->assertDontSee('SAR', false);
    }

    public function test_show_page_displays_scenarios_and_calculation(): void
    {
        $prediction = $this->createCompletedPrediction();

        $response = $this->actingAs($this->user)->get(route('ai.recommendations.show', $prediction));

        $response->assertOk();
        $response->assertSee('Calculated Market Price');
        $response->assertSee(' USD', false);
        $response->assertSee('Final Recommended Bid Price');
        $response->assertSee('Why this confidence score?');
        $response->assertSee('Detailed explanation is available for new recommendations only.');
        $response->assertSee('Competitive Position scores are estimated positioning indicators');
        $response->assertSee('Aggressive');
        $response->assertSee('Balanced');
        $response->assertSee('Conservative');
        $response->assertSee('Back to Recommendation History');
    }

    public function test_show_page_displays_confidence_breakdown_for_new_predictions(): void
    {
        $this->seedStats();

        $response = $this->actingAs($this->user)->post(route('ai.recommendations.store'), $this->recommendationPayload([
            'quantity_unit' => 'tablets',
        ]));

        $prediction = Prediction::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('ai.recommendations.show', $prediction));

        $show = $this->actingAs($this->user)->get(route('ai.recommendations.show', $prediction));

        $show->assertOk();
        $show->assertSee('Why this confidence score?');
        $show->assertSee('Historical Data');
        $show->assertSee('Recent Market Data');
        $show->assertSee('Market Stability');
        $show->assertSee('Country-Level Data');
        $show->assertDontSee('Detailed explanation is available for new recommendations only.', false);
        $show->assertSee('Why this risk level?');
        $show->assertSee('competitive position');
        $show->assertSee('Market Statistics');
        $show->assertSee('Tender Recommendation Context');
        $show->assertSee('This recommendation was generated for the tender above.');
    }

    public function test_show_page_displays_tender_context_banner(): void
    {
        $this->seedStats();
        $this->testTender->update(['title' => 'National Pharma Tender 2024', 'tender_number' => 'T-2024-001']);

        $response = $this->actingAs($this->user)->post(route('ai.recommendations.store'), $this->recommendationPayload());
        $prediction = Prediction::query()->latest('id')->firstOrFail();

        $show = $this->actingAs($this->user)->get(route('ai.recommendations.show', $prediction));

        $show->assertOk();
        $show->assertSee('National Pharma Tender 2024');
        $show->assertSee('T-2024-001');
        $show->assertSee('Saudi Arabia');
        $show->assertSee('AI Strategic Insights');
    }

    public function test_old_prediction_without_tender_shows_fallback_message(): void
    {
        $prediction = $this->createCompletedPrediction();

        $response = $this->actingAs($this->user)->get(route('ai.recommendations.show', $prediction));

        $response->assertOk();
        $response->assertSee('Tender details are unavailable for older recommendations.');
    }

    public function test_old_prediction_without_breakdown_still_renders(): void
    {
        $prediction = $this->createCompletedPrediction([
            'confidence_score' => 55,
        ]);

        $response = $this->actingAs($this->user)->get(route('ai.recommendations.show', $prediction));

        $response->assertOk();
        $response->assertSee('55%');
        $response->assertSee('Detailed explanation is available for new recommendations only.');
        $response->assertSee('Why this risk level?');
    }

    public function test_show_page_always_displays_ai_insights_section(): void
    {
        $prediction = $this->createCompletedPrediction();

        $response = $this->actingAs($this->user)->get(route('ai.recommendations.show', $prediction));

        $response->assertOk();
        $response->assertSee('AI Strategic Insights');
        $response->assertSee('AI insights are not available for this recommendation.');
    }

    public function test_show_page_displays_structured_ai_insights(): void
    {
        $prediction = $this->createCompletedPrediction([
            'ai_response_raw' => [
                'insights_status' => 'success',
                'insights' => $this->sampleInsights(),
            ],
            'ai_narrative_generated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('ai.recommendations.show', $prediction));

        $response->assertOk();
        $response->assertSee('Market Overview');
        $response->assertSee($this->sampleInsights()['market_overview']);
    }

    public function test_result_page_displays_skipped_message_when_ai_disabled(): void
    {
        $prediction = $this->createCompletedPrediction([
            'openai_called' => false,
            'ai_response_raw' => [
                'insights_status' => 'skipped',
                'message' => 'AI insights are not enabled.',
            ],
        ]);

        $response = $this->actingAs($this->user)->get(route('ai.recommendations.show', $prediction));

        $response->assertOk();
        $response->assertSee('AI insights are not enabled.');
    }

    public function test_guest_users_cannot_access_ai_recommendation_page(): void
    {
        $this->get(route('ai.recommendations.create'))
            ->assertRedirect(route('login'));

        $this->post(route('ai.recommendations.store'), [])
            ->assertRedirect(route('login'));
    }

    public function test_prediction_history_lists_predictions(): void
    {
        $this->createCompletedPrediction();

        $response = $this->actingAs($this->user)->get(route('predictions.index'));

        $response->assertOk();
        $response->assertSee('UI Test Drug');
        $response->assertSee('View');
    }

    public function test_prediction_history_filters_by_risk_status_source_and_search(): void
    {
        $low = $this->createCompletedPrediction(['risk_level' => 'low', 'status' => 'completed']);
        $high = $this->createCompletedPrediction(['risk_level' => 'high', 'status' => 'failed']);

        $this->actingAs($this->user)
            ->get(route('predictions.index', ['risk_level' => 'high']))
            ->assertOk()
            ->assertSee(Str::limit($high->uuid, 8, ''))
            ->assertDontSee(Str::limit($low->uuid, 8, ''));

        $this->actingAs($this->user)
            ->get(route('predictions.index', ['status' => 'completed']))
            ->assertOk()
            ->assertSee(Str::limit($low->uuid, 8, ''))
            ->assertDontSee(Str::limit($high->uuid, 8, ''));

        $this->actingAs($this->user)
            ->get(route('predictions.index', ['source' => 'backend_only']))
            ->assertOk()
            ->assertSee('UI Test Drug');

        $this->actingAs($this->user)
            ->get(route('predictions.index', ['search' => 'UI Test']))
            ->assertOk()
            ->assertSee('UI Test Drug');
    }

    public function test_predictions_show_redirects_to_ai_recommendations_show(): void
    {
        $prediction = $this->createCompletedPrediction();

        $response = $this->actingAs($this->user)->get(route('predictions.show', $prediction));

        $response->assertRedirect(route('ai.recommendations.show', $prediction));
    }

    public function test_history_empty_state_when_no_predictions(): void
    {
        $response = $this->actingAs($this->user)->get(route('predictions.index'));

        $response->assertOk();
        $response->assertSee('No recommendations yet');
    }

    public function test_no_openai_in_ui_controllers(): void
    {
        foreach ([
            app_path('Http/Controllers/AIRecommendationController.php'),
            app_path('Http/Controllers/PredictionController.php'),
        ] as $file) {
            $contents = strtolower(file_get_contents($file));
            $this->assertStringNotContainsString('openai\\', $contents);
        }
    }

    protected function seedStats(): void
    {
        foreach (range(1, 5) as $i) {
            $tender = Tender::query()->create([
                'tender_number' => 'T-'.$i,
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
                'quantity' => 1000,
                'award_year' => 2020 + $i,
                'is_analytics_ready' => true,
                'excluded_from_stats' => false,
            ]);
        }

        app(PricingStatisticsService::class)->calculateForDrugCountry(
            $this->drug->id,
            $this->country->id,
        );
    }

    protected function enableNarratives(): void
    {
        app(SettingsService::class)->setEncrypted('ai.api_key', 'sk-test-secret-key-12345');
        app(SettingsService::class)->updateGroup('ai', [
            'ai.enable_narrative' => ['value' => true, 'type' => 'boolean'],
            'ai.default_model' => ['value' => 'gpt-4o-mini'],
            'ai.temperature' => ['value' => 0.2, 'type' => 'float'],
            'ai.max_tokens' => ['value' => 400, 'type' => 'integer'],
            'ai.timeout_seconds' => ['value' => 30, 'type' => 'integer'],
            'ai.narrative_min_confidence' => ['value' => 50, 'type' => 'integer'],
        ]);
    }

    protected function fakeOpenAi(array|string $content): void
    {
        $payload = is_array($content) ? json_encode($content) : $content;

        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => ['content' => $payload],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 30,
                'completion_tokens' => 12,
                'total_tokens' => 42,
            ],
        ], 200)]);
    }

    /**
     * @return array<string, string>
     */
    protected function sampleInsights(): array
    {
        return [
            'market_overview' => 'The market shows stable pricing with sufficient historical award data to support the recommendation.',
            'competition_analysis' => 'Competition appears moderate with several distinct winners participating in recent awards.',
            'discount_review' => 'The applied discount positions the bid competitively while preserving reasonable margin headroom.',
            'risk_commentary' => 'Primary risks include limited recent award volume and broader country-level fallback scope.',
            'strategic_recommendation' => 'A balanced bidding approach aligns with current market conditions and data confidence.',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createCompletedPrediction(array $overrides = []): Prediction
    {
        if (! PricingStatistic::query()->exists()) {
            $this->seedStats();
        }

        $prediction = Prediction::query()->create(array_merge([
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
            'rationale' => 'Backend test rationale.',
            'completed_at' => now(),
        ], $overrides));

        PredictionCalculation::query()->create([
            'prediction_id' => $prediction->id,
            'weighted_average_price' => 12,
            'median_price' => 11,
            'last_winning_price' => 13,
            'average_price' => 12,
            'recommended_price' => 12.5,
            'historical_award_count' => 5,
            'outlier_count' => 0,
            'quantity_factor' => 1,
            'price_trend' => 'stable',
        ]);

        foreach (['aggressive', 'balanced', 'conservative'] as $name) {
            PredictionScenario::query()->create([
                'prediction_id' => $prediction->id,
                'scenario_name' => $name,
                'recommended_price' => 12,
                'win_probability' => 75,
                'risk_level' => $prediction->risk_level,
                'is_recommended' => $name === 'balanced',
                'source' => 'backend_template',
            ]);
        }

        return $prediction;
    }
}
