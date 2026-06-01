<?php

namespace Tests\Feature;

use App\Models\PredictionContextSnapshot;
use App\Models\Tender;
use App\Services\Prediction\PredictionOrchestratorService;
use App\Services\Prediction\TenderRecommendationContextService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenderRecommendations;
use Tests\TestCase;

class TenderRecommendationContextTest extends TestCase
{
    use CreatesTenderRecommendations;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RegionSeeder::class,
            CurrencySeeder::class,
            CountrySeeder::class,
            SettingSeeder::class,
        ]);

        $this->user = \App\Models\User::factory()->create();
        $this->country = \App\Models\Country::query()->where('name', 'Saudi Arabia')->firstOrFail();
        $this->currency = \App\Models\Currency::query()->where('code', 'USD')->firstOrFail();
        $this->drug = \App\Models\StandardizedDrug::query()->create([
            'code' => 'TCTX-001',
            'inn' => 'Context Drug',
            'display_name' => 'Context Drug',
            'is_active' => true,
            'source' => 'test',
        ]);
        $this->testTender = $this->createTestTender([
            'title' => 'Context Tender',
            'tender_number' => 'CTX-001',
        ]);
    }

    public function test_tender_context_service_builds_snapshot(): void
    {
        $snapshot = app(TenderRecommendationContextService::class)->buildTenderSnapshot($this->testTender->id);

        $this->assertNotNull($snapshot);
        $this->assertSame('Context Tender', $snapshot['title']);
        $this->assertSame('CTX-001', $snapshot['tender_number']);
        $this->assertSame('Saudi Arabia', $snapshot['country_name']);
    }

    public function test_context_snapshot_includes_tender_metadata(): void
    {
        foreach (range(1, 5) as $i) {
            $tender = Tender::query()->create([
                'tender_number' => 'T-'.$i,
                'country_id' => $this->country->id,
                'year' => 2020 + $i,
                'status' => 'active',
            ]);
            $item = \App\Models\TenderItem::query()->create([
                'tender_id' => $tender->id,
                'standardized_drug_id' => $this->drug->id,
            ]);
            \App\Models\BidRecord::query()->create([
                'tender_item_id' => $item->id,
                'tender_id' => $tender->id,
                'standardized_drug_id' => $this->drug->id,
                'country_id' => $this->country->id,
                'company_id' => \App\Models\Company::query()->create([
                    'name' => 'Co '.$i,
                    'normalized_name' => 'co '.$i,
                    'is_active' => true,
                    'source' => 'test',
                ])->id,
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

        app(\App\Services\Statistics\PricingStatisticsService::class)->calculateForDrugCountry(
            $this->drug->id,
            $this->country->id,
        );

        $prediction = app(PredictionOrchestratorService::class)->run($this->user, $this->recommendationPayload());

        $snapshot = PredictionContextSnapshot::query()->where('prediction_id', $prediction->id)->firstOrFail();

        $this->assertNotNull($snapshot->snapshot_data['tender_context']);
        $this->assertSame($this->testTender->id, $snapshot->snapshot_data['tender_context']['tender_id']);
        $this->assertArrayHasKey('tender_stats_availability', $snapshot->snapshot_data);
    }
}
