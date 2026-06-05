<?php

namespace Tests\Feature;

use App\Enums\PredictionFallbackLevel;
use App\Models\BidRecord;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Prediction;
use App\Models\PredictionContextSnapshot;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Models\TenderItem;
use App\Models\User;
use App\Services\Prediction\PredictionCalculationService;
use App\Services\Statistics\PricingStatisticsService;
use App\Services\Tender\TenderGroupKeyService;
use App\Services\Tender\TenderGroupService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenderRecommendations;
use Tests\TestCase;

class TenderGroupRecommendationTest extends TestCase
{
    use CreatesTenderRecommendations;
    use RefreshDatabase;

    protected User $user;

    protected Country $country;

    protected StandardizedDrug $drugInGroup;

    protected StandardizedDrug $drugOutsideGroup;

    protected Currency $currency;

    protected Company $company;

    protected string $groupKey = 'KIMADIA';

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
        $this->company = Company::query()->create([
            'name' => 'Group Co',
            'normalized_name' => 'group co',
            'is_active' => true,
            'source' => 'test',
        ]);

        $this->drugInGroup = StandardizedDrug::query()->create([
            'code' => 'KIM-001',
            'inn' => 'Kim Drug',
            'display_name' => 'Kim Group Drug',
            'is_active' => true,
            'source' => 'test',
        ]);

        $this->drugOutsideGroup = StandardizedDrug::query()->create([
            'code' => 'OUT-001',
            'inn' => 'Outside Drug',
            'display_name' => 'Outside Group Drug',
            'is_active' => true,
            'source' => 'test',
        ]);

        $this->seedKimadiaTenders();
        $this->seedCountryStatsForOutsideDrug();
    }

    public function test_kimadia_tenders_group_into_single_program(): void
    {
        $groups = app(TenderGroupService::class)->listGroups();
        $kimadia = $groups->firstWhere('group_key', $this->groupKey);

        $this->assertNotNull($kimadia);
        $this->assertSame(3, $kimadia['tender_count']);
        $this->assertSame([2023, 2024, 2025], $kimadia['years']);
        $this->assertSame('2023–2025', $kimadia['years_label']);
    }

    public function test_tender_group_drugs_endpoint_returns_only_group_products(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('ai.recommendations.tender-groups.drugs', ['groupKey' => $this->groupKey]));

        $response->assertOk();
        $response->assertJsonPath('group.group_key', $this->groupKey);
        $drugIds = collect($response->json('drugs'))->pluck('drug_id')->all();

        $this->assertContains($this->drugInGroup->id, $drugIds);
        $this->assertNotContains($this->drugOutsideGroup->id, $drugIds);
    }

    public function test_form_rejects_drug_outside_selected_tender_group(): void
    {
        $response = $this->actingAs($this->user)->post(route('ai.recommendations.store'), [
            'tender_group_key' => $this->groupKey,
            'standardized_drug_id' => $this->drugOutsideGroup->id,
            'quantity' => 1000,
            'quantity_unit' => 'units',
            'discount_percentage' => 0,
        ]);

        $response->assertSessionHasErrors('standardized_drug_id');
        $this->assertSame(0, Prediction::query()->count());
    }

    public function test_prediction_prefers_tender_group_history_before_country_fallback(): void
    {
        $result = app(PredictionCalculationService::class)->calculate(
            $this->drugInGroup->id,
            $this->country->id,
            1000,
            null,
            0,
            $this->groupKey,
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(PredictionFallbackLevel::TenderGroup, $result['fallback_level']);
        $this->assertSame($this->groupKey, $result['calculation_details']['tender_group_key']);
    }

    public function test_store_persists_tender_group_context_and_representative_tender(): void
    {
        $this->seedCountryStatsForGroupDrug();

        $response = $this->actingAs($this->user)->post(route('ai.recommendations.store'), [
            'tender_group_key' => $this->groupKey,
            'standardized_drug_id' => $this->drugInGroup->id,
            'quantity' => 5000,
            'quantity_unit' => 'units',
            'discount_percentage' => 0,
        ]);

        $prediction = Prediction::query()->with('contextSnapshots')->first();
        $response->assertRedirect(route('ai.recommendations.show', $prediction));
        $this->assertNotNull($prediction->tender_id);

        $snapshot = PredictionContextSnapshot::query()
            ->where('prediction_id', $prediction->id)
            ->first();

        $this->assertSame($this->groupKey, $snapshot->snapshot_data['tender_group_key'] ?? null);
        $this->assertSame($this->groupKey, $snapshot->snapshot_data['tender_group_context']['group_key'] ?? null);
        $this->assertNotEmpty($snapshot->snapshot_data['tender_specific_awards'] ?? []);
    }

    public function test_existing_prediction_without_tender_group_still_renders(): void
    {
        $prediction = $this->createCompletedPrediction();

        $this->actingAs($this->user)
            ->get(route('ai.recommendations.show', $prediction))
            ->assertOk()
            ->assertSee('Tender details are unavailable for older recommendations.');
    }

    protected function seedKimadiaTenders(): void
    {
        foreach ([2023, 2024, 2025] as $year) {
            $tender = Tender::query()->create([
                'tender_number' => "KIMADIA-{$year}",
                'title' => "Iraq Tender KIMADIA-{$year}",
                'country_id' => $this->country->id,
                'year' => $year,
                'status' => 'active',
            ]);

            $item = TenderItem::query()->create([
                'tender_id' => $tender->id,
                'standardized_drug_id' => $this->drugInGroup->id,
            ]);

            BidRecord::query()->create([
                'tender_item_id' => $item->id,
                'tender_id' => $tender->id,
                'standardized_drug_id' => $this->drugInGroup->id,
                'country_id' => $this->country->id,
                'company_id' => $this->company->id,
                'currency_id' => $this->currency->id,
                'bid_status' => 'awarded',
                'is_winner' => true,
                'row_type' => 'winning_bid',
                'price_usd' => 10 + ($year - 2022),
                'quantity' => 1000,
                'award_year' => $year,
                'is_analytics_ready' => true,
                'excluded_from_stats' => false,
            ]);
        }
    }

    protected function seedCountryStatsForGroupDrug(): void
    {
        app(PricingStatisticsService::class)->calculateForDrugCountry(
            $this->drugInGroup->id,
            $this->country->id,
        );
    }

    protected function seedCountryStatsForOutsideDrug(): void
    {
        $otherTender = Tender::query()->create([
            'tender_number' => 'OTHER-2024',
            'title' => 'Other Tender 2024',
            'country_id' => $this->country->id,
            'year' => 2024,
            'status' => 'active',
        ]);

        $item = TenderItem::query()->create([
            'tender_id' => $otherTender->id,
            'standardized_drug_id' => $this->drugOutsideGroup->id,
        ]);

        BidRecord::query()->create([
            'tender_item_id' => $item->id,
            'tender_id' => $otherTender->id,
            'standardized_drug_id' => $this->drugOutsideGroup->id,
            'country_id' => $this->country->id,
            'company_id' => $this->company->id,
            'currency_id' => $this->currency->id,
            'bid_status' => 'awarded',
            'is_winner' => true,
            'row_type' => 'winning_bid',
            'price_usd' => 20,
            'quantity' => 500,
            'award_year' => 2024,
            'is_analytics_ready' => true,
            'excluded_from_stats' => false,
        ]);

        app(PricingStatisticsService::class)->calculateForDrugCountry(
            $this->drugOutsideGroup->id,
            $this->country->id,
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createCompletedPrediction(array $overrides = []): Prediction
    {
        return Prediction::query()->create(array_merge([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->user->id,
            'standardized_drug_id' => $this->drugInGroup->id,
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
        ], $overrides));
    }
}
