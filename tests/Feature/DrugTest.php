<?php

namespace Tests\Feature;

use App\Models\BidRecord;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\DrugAlias;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\PricingStatistic;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Models\TenderItem;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrugTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Country $country;

    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RegionSeeder::class,
            CurrencySeeder::class,
            CountrySeeder::class,
        ]);

        $this->user = User::factory()->create();
        $this->country = Country::query()->where('name', 'Saudi Arabia')->firstOrFail();
        $this->currency = Currency::query()->where('code', 'USD')->firstOrFail();
    }

    public function test_drugs_index_lists_real_standardized_drugs(): void
    {
        $record = $this->createBidRecord([
            'drug_code' => 'DRG-REAL-001',
            'drug_display' => 'Real Drug Alpha',
        ]);

        $this->actingAs($this->user)
            ->get(route('drugs.index'))
            ->assertOk()
            ->assertSee('Drug Intelligence')
            ->assertSee('DRG-REAL-001')
            ->assertSee('Real Drug Alpha');
    }

    public function test_search_by_code_inn_alias_works(): void
    {
        $record = $this->createBidRecord([
            'drug_code' => 'SEARCH-CODE-77',
            'drug_display' => 'Visible Drug Name',
        ]);
        DrugAlias::query()->create([
            'standardized_drug_id' => $record->standardized_drug_id,
            'alias_value' => 'Unique Alias Term',
            'normalized_alias' => 'unique alias term',
            'alias_type' => 'trade_name',
            'source' => 'test',
        ]);
        $this->createBidRecord(['drug_code' => 'OTHER-DRUG-88', 'drug_display' => 'Other Drug']);

        $this->actingAs($this->user)
            ->get(route('drugs.index', ['search' => 'Unique Alias']))
            ->assertOk()
            ->assertSee('SEARCH-CODE-77')
            ->assertDontSee('OTHER-DRUG-88');

        $this->actingAs($this->user)
            ->get(route('drugs.index', ['search' => 'SEARCH-CODE']))
            ->assertOk()
            ->assertSee('SEARCH-CODE-77');
    }

    public function test_filter_by_country_works(): void
    {
        $this->createBidRecord([
            'country' => $this->country,
            'drug_code' => 'SA-DRUG-01',
        ]);
        $uae = Country::query()->where('name', 'United Arab Emirates')->firstOrFail();
        $this->createBidRecord([
            'country' => $uae,
            'drug_code' => 'UAE-DRUG-01',
        ]);

        $this->actingAs($this->user)
            ->get(route('drugs.index', ['country_id' => $this->country->id]))
            ->assertOk()
            ->assertSee('SA-DRUG-01')
            ->assertDontSee('UAE-DRUG-01');
    }

    public function test_filter_by_company_works(): void
    {
        $recordA = $this->createBidRecord([
            'company_name' => 'Drug Filter Co A',
            'drug_code' => 'DF-A-01',
        ]);
        $this->createBidRecord([
            'company_name' => 'Drug Filter Co B',
            'drug_code' => 'DF-B-01',
        ]);

        $this->actingAs($this->user)
            ->get(route('drugs.index', ['company_id' => $recordA->company_id]))
            ->assertOk()
            ->assertSee('DF-A-01')
            ->assertDontSee('DF-B-01');
    }

    public function test_drug_show_displays_kpis(): void
    {
        $record = $this->createBidRecord([
            'drug_code' => 'KPI-DRUG-01',
            'drug_display' => 'KPI Drug Profile',
            'bid_status' => 'awarded',
            'price_usd' => 275,
        ]);

        $this->actingAs($this->user)
            ->get(route('drugs.show', $record->standardizedDrug))
            ->assertOk()
            ->assertSee('KPI Drug Profile')
            ->assertSee('Bid Records')
            ->assertSee('Avg Price USD');
    }

    public function test_drug_show_displays_pricing_statistics(): void
    {
        $record = $this->createBidRecord([
            'drug_code' => 'STAT-DRUG-01',
            'drug_display' => 'Stats Drug',
        ]);

        PricingStatistic::query()->create([
            'standardized_drug_id' => $record->standardized_drug_id,
            'country_id' => $this->country->id,
            'currency_id' => $this->currency->id,
            'award_count' => 5,
            'median_unit_price' => 120.50,
            'avg_unit_price' => 115.00,
            'trend_direction' => 'up',
            'trend_pct' => 3.5,
            'calculated_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('drugs.show', $record->standardizedDrug))
            ->assertOk()
            ->assertSee('Pricing Statistics')
            ->assertSee('Saudi Arabia')
            ->assertSee('120.50');
    }

    public function test_drug_show_groups_companies(): void
    {
        $drug = StandardizedDrug::query()->create([
            'code' => 'GRP-CO-DRG',
            'inn' => 'Grouped Inn',
            'display_name' => 'Grouped Drug',
            'is_active' => true,
            'source' => 'test',
        ]);

        $this->createBidRecord([
            'drug' => $drug,
            'company_name' => 'Drug Company Alpha',
        ]);
        $this->createBidRecord([
            'drug' => $drug,
            'company_name' => 'Drug Company Beta',
            'drug_code' => 'GRP-CO-DRG',
        ]);

        $this->actingAs($this->user)
            ->get(route('drugs.show', $drug))
            ->assertOk()
            ->assertSee('Drug Company Summary')
            ->assertSee('Drug Company Alpha')
            ->assertSee('Drug Company Beta');
    }

    public function test_drug_show_groups_countries(): void
    {
        $drug = StandardizedDrug::query()->create([
            'code' => 'GRP-CT-DRG',
            'display_name' => 'Multi Country Drug',
            'is_active' => true,
            'source' => 'test',
        ]);

        $this->createBidRecord([
            'drug' => $drug,
            'country' => $this->country,
            'drug_code' => 'GRP-CT-DRG',
        ]);
        $uae = Country::query()->where('name', 'United Arab Emirates')->firstOrFail();
        $this->createBidRecord([
            'drug' => $drug,
            'country' => $uae,
            'drug_code' => 'GRP-CT-DRG',
        ]);

        $this->actingAs($this->user)
            ->get(route('drugs.show', $drug))
            ->assertOk()
            ->assertSee('Drug Country Summary')
            ->assertSee('Saudi Arabia')
            ->assertSee('United Arab Emirates');
    }

    public function test_empty_state_when_no_drugs_exist(): void
    {
        $this->actingAs($this->user)
            ->get(route('drugs.index'))
            ->assertOk()
            ->assertSee('No drugs yet')
            ->assertSee('Upload and materialize historical data first')
            ->assertSee(route('uploads.index'))
            ->assertSee(route('management.index'));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function createBidRecord(array $options = []): BidRecord
    {
        $country = $options['country'] ?? $this->country;

        $drug = $options['drug'] ?? StandardizedDrug::query()->create([
            'code' => $options['drug_code'] ?? 'DR-'.uniqid(),
            'inn' => $options['drug_inn'] ?? 'Test Inn',
            'display_name' => $options['drug_display'] ?? 'Test Drug',
            'is_active' => true,
            'source' => 'test',
        ]);

        $companyName = $options['company_name'] ?? 'Test Company';
        $company = Company::query()->firstOrCreate(
            ['normalized_name' => strtolower($companyName)],
            [
                'name' => $companyName,
                'is_active' => true,
                'source' => 'test',
            ],
        );

        $tender = Tender::query()->create([
            'tender_number' => $options['tender_number'] ?? 'T-'.uniqid(),
            'country_id' => $country->id,
            'year' => $options['tender_year'] ?? 2024,
            'version' => 'v1',
            'status' => 'active',
        ]);

        $item = TenderItem::query()->create([
            'tender_id' => $tender->id,
            'standardized_drug_id' => $drug->id,
            'description' => $options['product_description'] ?? 'Test product line',
        ]);

        $batch = ImportBatch::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'filename' => 'drug-test.csv',
            'original_filename' => 'drug-test.csv',
            'file_path' => 'imports/drug-test.csv',
            'file_hash' => hash('sha256', uniqid('', true)),
            'row_count' => 1,
            'status' => 'completed',
            'source_type' => 'csv',
        ]);

        $importRow = ImportRow::query()->create([
            'import_batch_id' => $batch->id,
            'row_number' => 1,
            'row_hash' => hash('sha256', uniqid('', true)),
            'raw_code' => $drug->code,
            'raw_inn' => $drug->inn,
            'raw_product_name' => 'Raw product',
            'raw_country' => $country->name,
            'raw_tender_number' => $tender->tender_number,
            'raw_company_name' => $company->name,
            'raw_year' => (string) ($options['tender_year'] ?? 2024),
            'validation_status' => 'valid',
            'standardization_status' => 'auto_approved',
            'row_type' => 'winning_bid',
            'raw_data' => ['note' => 'immutable'],
        ]);

        return BidRecord::query()->create([
            'tender_item_id' => $item->id,
            'tender_id' => $tender->id,
            'standardized_drug_id' => $drug->id,
            'company_id' => $company->id,
            'country_id' => $country->id,
            'currency_id' => $this->currency->id,
            'bid_status' => $options['bid_status'] ?? 'awarded',
            'is_winner' => $options['is_winner'] ?? true,
            'row_type' => 'winning_bid',
            'price_usd' => $options['price_usd'] ?? 100,
            'original_awarded_price' => $options['original_awarded_price'] ?? 95,
            'quantity' => $options['quantity'] ?? 1000,
            'tender_value' => $options['tender_value'] ?? 100000,
            'award_year' => $options['award_year'] ?? 2024,
            'source_import_row_id' => $importRow->id,
            'import_batch_id' => $batch->id,
            'is_analytics_ready' => $options['is_analytics_ready'] ?? true,
            'excluded_from_stats' => $options['excluded_from_stats'] ?? false,
        ]);
    }
}
