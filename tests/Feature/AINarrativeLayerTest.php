<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\BidRecord;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Prediction;
use App\Models\PredictionCalculation;
use App\Models\PredictionContextSnapshot;
use App\Models\PredictionScenario;
use App\Models\PricingStatistic;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Models\TenderItem;
use App\Models\User;
use App\Services\AI\OpenAIService;
use App\Services\AI\PredictionNarrativeService;
use App\Services\Prediction\PredictionOrchestratorService;
use App\Services\Settings\SettingsService;
use App\Services\Statistics\PricingStatisticsService;
use App\Support\RecommendationCurrency;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesTenderRecommendations;
use Tests\TestCase;

class AINarrativeLayerTest extends TestCase
{
    use CreatesTenderRecommendations;
    use RefreshDatabase;

    protected User $user;

    protected Country $country;

    protected Currency $currency;

    protected StandardizedDrug $drug;

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
            'code' => 'AI-001',
            'inn' => 'AI Drug',
            'display_name' => 'AI Test Drug',
            'is_active' => true,
            'source' => 'test',
        ]);
        $this->company = Company::query()->create([
            'name' => 'AI Co',
            'normalized_name' => 'ai co',
            'is_active' => true,
            'source' => 'test',
        ]);
        $this->testTender = $this->createTestTender();
    }

    public function test_openai_service_handles_missing_key_safely(): void
    {
        $result = app(OpenAIService::class)->chat([
            ['role' => 'user', 'content' => 'Test'],
        ], ['feature' => 'prediction_narrative', 'user_id' => $this->user->id]);

        $this->assertFalse($result['success']);
        $this->assertSame('missing_api_key', $result['error_code']);
        $this->assertDatabaseHas('ai_usage_logs', [
            'feature' => 'prediction_narrative',
            'status' => 'failure',
        ]);
    }

    public function test_insights_prompt_includes_usd_currency_context(): void
    {
        $this->enableNarratives();
        $this->fakeOpenAi($this->sampleInsights());
        $this->seedStats();

        app(PredictionOrchestratorService::class)->run($this->user, $this->recommendationPayload());

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            $userMessage = $body['messages'][1]['content'] ?? '';

            return str_contains($userMessage, '"pricing_currency": "USD"')
                && str_contains($userMessage, '"currency": "USD"');
        });
    }

    public function test_insights_generation_saves_without_changing_backend_price(): void
    {
        $this->enableNarratives();
        $this->fakeOpenAi($this->sampleInsights());
        $this->seedStats();

        $prediction = app(PredictionOrchestratorService::class)->run($this->user, $this->recommendationPayload())->fresh();

        $this->assertEquals('completed', $prediction->status);
        $this->assertSame(RecommendationCurrency::usdCurrencyId(), $prediction->currency_id);
        $this->assertNotNull($prediction->backend_recommended_price);
        $this->assertEquals($prediction->backend_recommended_price, $prediction->recommended_price);
        $this->assertSame('backend_only', $prediction->source);
        $this->assertSame('success', $prediction->ai_response_raw['insights_status'] ?? null);
        $this->assertNotNull($prediction->ai_response_raw['insights']['market_overview'] ?? null);
        $this->assertSame('gpt-4o-mini', $prediction->ai_model_used);
        $this->assertSame(42, $prediction->ai_tokens_used);
    }

    public function test_prediction_still_succeeds_if_ai_fails(): void
    {
        $this->enableNarratives();
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'rate limited', 'code' => 'rate_limit_exceeded']], 429)]);
        $this->seedStats();

        $prediction = app(PredictionOrchestratorService::class)->run($this->user, $this->recommendationPayload())->fresh();

        $this->assertEquals('completed', $prediction->status);
        $this->assertNull($prediction->ai_response_raw['insights'] ?? null);
        $this->assertTrue($prediction->openai_called);
        $this->assertSame('unavailable', $prediction->ai_response_raw['insights_status'] ?? null);
    }

    public function test_ai_disabled_skips_insights_without_http_call(): void
    {
        Http::fake();
        $this->seedStats();

        $prediction = app(PredictionOrchestratorService::class)->run($this->user, $this->recommendationPayload())->fresh();

        Http::assertNothingSent();
        $this->assertFalse($prediction->openai_called);
        $this->assertSame('skipped', $prediction->ai_response_raw['insights_status'] ?? null);
    }

    public function test_low_confidence_prediction_skips_insights(): void
    {
        $this->enableNarratives(['ai.narrative_min_confidence' => ['value' => 99, 'type' => 'integer']]);
        Http::fake();
        $this->seedStats();

        $prediction = app(PredictionOrchestratorService::class)->run($this->user, $this->recommendationPayload())->fresh();

        Http::assertNothingSent();
        $this->assertFalse($prediction->openai_called);
        $this->assertSame('skipped', $prediction->ai_response_raw['insights_status'] ?? null);
        $this->assertStringContainsString('data confidence', strtolower($prediction->ai_response_raw['message']));
    }

    public function test_ai_usage_log_created_for_success(): void
    {
        $this->enableNarratives();
        $this->fakeOpenAi($this->sampleInsights());
        $this->seedStats();

        $prediction = app(PredictionOrchestratorService::class)->run($this->user, $this->recommendationPayload());

        $this->assertDatabaseHas('ai_usage_logs', [
            'prediction_id' => $prediction->id,
            'feature' => 'prediction_narrative',
            'status' => 'success',
            'total_tokens' => 42,
        ]);
    }

    public function test_prediction_result_page_displays_ai_strategic_insights(): void
    {
        $prediction = $this->createCompletedPrediction([
            'ai_narrative' => 'Legacy narrative text.',
            'ai_narrative_generated_at' => now(),
            'ai_model_used' => 'gpt-4o-mini',
            'ai_tokens_used' => 42,
            'ai_response_raw' => [
                'insights_status' => 'success',
                'insights' => $this->sampleInsights(),
            ],
        ]);

        $this->actingAs($this->user)
            ->get(route('ai.recommendations.show', $prediction))
            ->assertOk()
            ->assertSee('AI Strategic Insights')
            ->assertSee('Market Overview')
            ->assertSee('Competition Analysis')
            ->assertSee('Discount Review')
            ->assertSee('Risk Commentary')
            ->assertSee('Strategic Recommendation')
            ->assertSee($this->sampleInsights()['market_overview']);
    }

    public function test_cached_insights_are_not_regenerated_without_force(): void
    {
        $this->enableNarratives();
        Http::fake();

        $prediction = $this->createCompletedPrediction([
            'ai_response_raw' => [
                'insights_status' => 'success',
                'insights' => $this->sampleInsights(),
            ],
            'ai_narrative_generated_at' => now(),
        ]);

        $result = app(PredictionNarrativeService::class)->generateForPrediction($prediction);

        Http::assertNothingSent();
        $this->assertSame('cached', $result['status']);
    }

    public function test_regenerate_insights_route_forces_new_generation(): void
    {
        $this->enableNarratives();
        $this->fakeOpenAi($this->sampleInsights());

        $prediction = $this->createCompletedPrediction([
            'ai_response_raw' => [
                'insights_status' => 'success',
                'insights' => ['market_overview' => 'Old insight text for market overview section here.', 'competition_analysis' => 'Old competition insight text for testing purposes here.', 'discount_review' => 'Old discount review insight text for testing purposes.', 'risk_commentary' => 'Old risk commentary insight text for testing purposes.', 'strategic_recommendation' => 'Old strategic recommendation insight text for testing.'],
            ],
        ]);

        $this->actingAs($this->user)
            ->post(route('ai.recommendations.regenerate-insights', $prediction))
            ->assertRedirect(route('ai.recommendations.show', $prediction))
            ->assertSessionHas('success');

        $prediction->refresh();
        $this->assertSame($this->sampleInsights()['market_overview'], $prediction->ai_response_raw['insights']['market_overview'] ?? null);
    }

    public function test_api_timeout_handled_gracefully(): void
    {
        $this->enableNarratives();
        Http::fake(fn () => throw new ConnectionException('Connection timed out.'));

        $result = app(OpenAIService::class)->chat([
            ['role' => 'user', 'content' => 'Test timeout'],
        ], ['feature' => 'prediction_narrative', 'user_id' => $this->user->id]);

        $this->assertFalse($result['success']);
        $this->assertSame('timeout_or_connection_error', $result['error_code']);
    }

    public function test_raw_api_key_is_not_exposed_in_response_logs_or_settings_page(): void
    {
        $this->enableNarratives();
        $this->fakeOpenAi($this->sampleInsights());

        $result = app(OpenAIService::class)->chat([
            ['role' => 'user', 'content' => 'Test key handling'],
        ], ['feature' => 'prediction_narrative', 'user_id' => $this->user->id]);

        $log = AiUsageLog::query()->latest('id')->firstOrFail();

        $this->assertStringNotContainsString('sk-test-secret-key-12345', json_encode($result));
        $this->assertStringNotContainsString('sk-test-secret-key-12345', json_encode($log->toArray()));

        $this->actingAs($this->user)
            ->get(route('settings.index', ['tab' => 'ai']))
            ->assertOk()
            ->assertDontSee('sk-test-secret-key-12345');
    }

    public function test_manual_narrative_test_route_uses_mock_payload(): void
    {
        $this->enableNarratives();
        $this->fakeOpenAi($this->sampleInsights());

        $this->actingAs($this->user)
            ->post(route('settings.ai.test-narrative'))
            ->assertRedirect(route('settings.index', ['tab' => 'ai']))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ai_usage_logs', [
            'feature' => 'prediction_narrative_test',
            'status' => 'success',
        ]);
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
     * @param  array<string, array{value: mixed, type?: string}>  $overrides
     */
    protected function enableNarratives(array $overrides = []): void
    {
        app(SettingsService::class)->setEncrypted('ai.api_key', 'sk-test-secret-key-12345');
        app(SettingsService::class)->updateGroup('ai', array_merge([
            'ai.enable_narrative' => ['value' => true, 'type' => 'boolean'],
            'ai.default_model' => ['value' => 'gpt-4o-mini'],
            'ai.temperature' => ['value' => 0.2, 'type' => 'float'],
            'ai.max_tokens' => ['value' => 400, 'type' => 'integer'],
            'ai.timeout_seconds' => ['value' => 30, 'type' => 'integer'],
            'ai.narrative_min_confidence' => ['value' => 50, 'type' => 'integer'],
        ], $overrides));
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

    protected function seedStats(): void
    {
        $this->ensureDrugExistsInTestTenderGroup();

        $testItem = TenderItem::query()->where('tender_id', $this->testTender->id)
            ->where('standardized_drug_id', $this->drug->id)
            ->firstOrFail();

        foreach (range(1, 6) as $i) {
            BidRecord::query()->create([
                'tender_item_id' => $testItem->id,
                'tender_id' => $this->testTender->id,
                'standardized_drug_id' => $this->drug->id,
                'country_id' => $this->country->id,
                'company_id' => $this->company->id,
                'currency_id' => $this->currency->id,
                'bid_status' => 'awarded',
                'is_winner' => true,
                'row_type' => 'winning_bid',
                'price_usd' => 10 + $i,
                'quantity' => 1000 * $i,
                'award_year' => 2019 + $i,
                'is_analytics_ready' => true,
                'excluded_from_stats' => false,
            ]);
        }

        app(PricingStatisticsService::class)->calculateForDrugCountry(
            $this->drug->id,
            $this->country->id,
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createCompletedPrediction(array $overrides = []): Prediction
    {
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
            'openai_called' => true,
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

        PredictionContextSnapshot::query()->create([
            'prediction_id' => $prediction->id,
            'snapshot_hash' => hash('sha256', 'test-snapshot'),
            'snapshot_data' => [
                'selected_stats_row' => [
                    'award_count' => 5,
                    'weighted_avg_unit_price' => 12,
                    'median_unit_price' => 11,
                    'last_unit_price' => 13,
                    'trend_direction' => 'stable',
                ],
                'competition_summary' => [
                    'distinct_winners' => 3,
                ],
                'tender_context' => [
                    'title' => 'Test Tender',
                    'tender_number' => 'T-001',
                    'country_name' => 'Saudi Arabia',
                ],
            ],
        ]);

        return $prediction;
    }
}
