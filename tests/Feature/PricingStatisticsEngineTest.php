<?php

namespace Tests\Feature;

use App\Enums\TrendDirection;
use App\Models\BidRecord;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\OutlierFlag;
use App\Models\PricingStatistic;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Models\TenderItem;
use App\Services\Statistics\OutlierDetectionService;
use App\Services\Statistics\PricingAggregationService;
use App\Services\Statistics\PricingStatisticsService;
use App\Services\Statistics\StatisticsRefreshService;
use App\Support\RecommendationCurrency;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingStatisticsEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Country $country;

    protected StandardizedDrug $drug;

    protected Currency $currency;

    protected Company $companyA;

    protected Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RegionSeeder::class,
            CurrencySeeder::class,
            CountrySeeder::class,
        ]);

        $this->country = Country::query()->where('name', 'Saudi Arabia')->firstOrFail();
        $this->currency = Currency::query()->where('code', 'USD')->firstOrFail();
        $this->drug = StandardizedDrug::query()->create([
            'code' => 'STAT-001',
            'inn' => 'Test Drug',
            'display_name' => 'Test Drug 500mg',
            'is_active' => true,
            'source' => 'test',
        ]);
        $this->companyA = Company::query()->create([
            'name' => 'Winner Alpha',
            'normalized_name' => 'winner alpha',
            'is_active' => true,
            'source' => 'test',
        ]);
        $this->companyB = Company::query()->create([
            'name' => 'Winner Beta',
            'normalized_name' => 'winner beta',
            'is_active' => true,
            'source' => 'test',
        ]);
    }

    public function test_pricing_statistics_currency_metadata_is_always_usd(): void
    {
        $sar = Currency::query()->where('code', 'SAR')->firstOrFail();

        foreach (range(1, 3) as $i) {
            $this->createBid([
                'price_usd' => 5 + $i,
                'award_year' => 2022 + $i,
                'currency_id' => $sar->id,
                'company_id' => $this->companyA->id,
            ]);
        }

        app(PricingStatisticsService::class)->calculateForDrugCountry($this->drug->id, $this->country->id);

        $statistic = PricingStatistic::query()->where('standardized_drug_id', $this->drug->id)
            ->where('country_id', $this->country->id)
            ->firstOrFail();

        $this->assertSame(RecommendationCurrency::usdCurrencyId(), $statistic->currency_id);
    }

    public function test_pricing_stats_only_use_analytics_ready_awarded_winner_records(): void
    {
        $this->createBid(['price_usd' => 10, 'award_year' => 2024, 'company_id' => $this->companyA->id]);
        $this->createBid([
            'price_usd' => 999,
            'award_year' => 2024,
            'is_analytics_ready' => false,
            'company_id' => $this->companyA->id,
        ]);
        $this->createBid([
            'price_usd' => 999,
            'award_year' => 2024,
            'excluded_from_stats' => true,
            'company_id' => $this->companyA->id,
        ]);
        $this->createBid([
            'price_usd' => 999,
            'award_year' => 2024,
            'bid_status' => 'lost',
            'is_winner' => false,
            'company_id' => $this->companyA->id,
        ]);
        $this->createBid([
            'price_usd' => 0,
            'award_year' => 2024,
            'company_id' => $this->companyA->id,
        ]);

        $service = app(PricingStatisticsService::class);
        $outcome = $service->calculateForDrugCountry($this->drug->id, $this->country->id);

        $this->assertFalse($outcome['skipped']);
        $this->assertEquals(1, $outcome['statistic']->award_count);
        $this->assertEquals(10.0, (float) $outcome['statistic']->avg_unit_price);
    }

    public function test_average_calculation(): void
    {
        $aggregation = app(PricingAggregationService::class);

        $this->assertEqualsWithDelta(15.0, $aggregation->average([10, 20]), 0.0001);
        $this->assertNull($aggregation->average([]));
    }

    public function test_median_calculation_with_odd_and_even_counts(): void
    {
        $aggregation = app(PricingAggregationService::class);

        $this->assertEqualsWithDelta(20.0, $aggregation->median([10, 20, 30]), 0.0001);
        $this->assertEqualsWithDelta(15.0, $aggregation->median([10, 20]), 0.0001);
    }

    public function test_weighted_average_gives_more_weight_to_recent_years(): void
    {
        $records = collect([
            $this->makeBidRecordStub(100, 2020),
            $this->makeBidRecordStub(100, 2024),
        ]);

        $weighted = app(PricingAggregationService::class)->weightedAverageUnitPrice($records);
        $simple = app(PricingAggregationService::class)->average([100, 100]);

        $this->assertEqualsWithDelta($simple, $weighted, 0.0001);

        $records = collect([
            $this->makeBidRecordStub(100, 2020),
            $this->makeBidRecordStub(200, 2024),
        ]);

        $weighted = app(PricingAggregationService::class)->weightedAverageUnitPrice($records);

        // (100*1 + 200*3) / 4 = 175
        $this->assertEqualsWithDelta(175.0, $weighted, 0.0001);
    }

    public function test_trend_rising_falling_and_stable(): void
    {
        $aggregation = app(PricingAggregationService::class);

        $rising = $aggregation->trendFromYearlyMedians([2022 => 100.0, 2024 => 110.0]);
        $this->assertEquals(TrendDirection::Rising, $rising['direction']);
        $this->assertEqualsWithDelta(10.0, $rising['pct'], 0.0001);

        $falling = $aggregation->trendFromYearlyMedians([2022 => 100.0, 2024 => 90.0]);
        $this->assertEquals(TrendDirection::Falling, $falling['direction']);

        $stable = $aggregation->trendFromYearlyMedians([2022 => 100.0, 2024 => 103.0]);
        $this->assertEquals(TrendDirection::Stable, $stable['direction']);

        $unknown = $aggregation->trendFromYearlyMedians([2024 => 100.0]);
        $this->assertEquals(TrendDirection::Unknown, $unknown['direction']);
        $this->assertNull($unknown['pct']);
    }

    public function test_distinct_winners_count_and_top_winner_company(): void
    {
        $this->createBid(['price_usd' => 10, 'award_year' => 2022, 'company_id' => $this->companyA->id]);
        $this->createBid(['price_usd' => 11, 'award_year' => 2023, 'company_id' => $this->companyA->id]);
        $this->createBid(['price_usd' => 12, 'award_year' => 2024, 'company_id' => $this->companyA->id]);
        $this->createBid(['price_usd' => 13, 'award_year' => 2024, 'company_id' => $this->companyB->id]);

        $stat = app(PricingStatisticsService::class)
            ->calculateForDrugCountry($this->drug->id, $this->country->id)['statistic'];

        $this->assertEquals(2, $stat->distinct_winners_count);
        $this->assertEquals($this->companyA->id, $stat->top_winner_company_id);
    }

    public function test_outlier_detection_using_iqr(): void
    {
        foreach ([10, 11, 12, 50] as $price) {
            $this->createBid([
                'price_usd' => $price,
                'award_year' => 2024,
                'company_id' => $this->companyA->id,
            ]);
        }

        $outlierBid = BidRecord::query()->where('price_usd', 50)->firstOrFail();

        $summary = app(OutlierDetectionService::class)->detectForDrugCountry(
            $this->drug->id,
            $this->country->id,
        );

        $this->assertEquals(1, $summary['outliers_flagged']);
        $this->assertDatabaseHas('outlier_flags', [
            'entity_type' => OutlierDetectionService::ENTITY_TYPE,
            'entity_id' => $outlierBid->id,
            'flag_type' => OutlierDetectionService::FLAG_TYPE,
            'is_resolved' => false,
        ]);

        $secondRun = app(OutlierDetectionService::class)->detectForDrugCountry(
            $this->drug->id,
            $this->country->id,
        );
        $this->assertEquals(0, $secondRun['outliers_flagged']);
        $this->assertEquals(1, OutlierFlag::query()->where('entity_id', $outlierBid->id)->count());
    }

    public function test_outlier_detection_skips_groups_with_fewer_than_four_records(): void
    {
        $this->createBid(['price_usd' => 10, 'award_year' => 2024, 'company_id' => $this->companyA->id]);
        $this->createBid(['price_usd' => 100, 'award_year' => 2024, 'company_id' => $this->companyA->id]);

        $summary = app(OutlierDetectionService::class)->detectForDrugCountry(
            $this->drug->id,
            $this->country->id,
        );

        $this->assertEquals(0, $summary['outliers_flagged']);
        $this->assertEquals(1, $summary['skipped_groups']);
    }

    public function test_stats_refresh_command_creates_pricing_statistics(): void
    {
        $this->createBid(['price_usd' => 20, 'award_year' => 2023, 'company_id' => $this->companyA->id]);
        $this->createBid(['price_usd' => 22, 'award_year' => 2024, 'company_id' => $this->companyA->id]);

        $this->artisan('stats:refresh', [
            '--drug' => $this->drug->id,
            '--country' => $this->country->id,
        ])->assertSuccessful();

        $this->assertDatabaseHas('pricing_statistics', [
            'standardized_drug_id' => $this->drug->id,
            'country_id' => $this->country->id,
            'award_count' => 2,
        ]);
    }

    public function test_statistics_refresh_service_dry_run_does_not_persist(): void
    {
        $this->createBid(['price_usd' => 15, 'award_year' => 2024, 'company_id' => $this->companyA->id]);

        app(StatisticsRefreshService::class)->refreshSubset(
            $this->drug->id,
            $this->country->id,
            persist: false,
            includeFallbacks: false,
        );

        $this->assertEquals(0, PricingStatistic::count());
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
            'company_id' => $this->companyA->id,
            'currency_id' => $this->currency->id,
            'bid_status' => 'awarded',
            'is_winner' => true,
            'row_type' => 'winning_bid',
            'price_usd' => 10,
            'award_year' => 2024,
            'is_analytics_ready' => true,
            'excluded_from_stats' => false,
        ], $overrides));
    }

    protected function makeBidRecordStub(float $price, ?int $year): BidRecord
    {
        $record = new BidRecord([
            'price_usd' => $price,
            'award_year' => $year,
        ]);

        return $record;
    }
}
