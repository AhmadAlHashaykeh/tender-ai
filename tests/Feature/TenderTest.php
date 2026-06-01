<?php

namespace Tests\Feature;

use App\Models\BidRecord;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\DrugAlias;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Models\TenderItem;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenderTest extends TestCase
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

    public function test_tenders_index_lists_real_tenders(): void
    {
        $record = $this->createBidRecord(['tender_number' => 'TND-REAL-001', 'tender_title' => 'Real Tender Alpha']);

        $this->actingAs($this->user)
            ->get(route('tenders.index'))
            ->assertOk()
            ->assertSee('Tender Intelligence')
            ->assertSee('TND-REAL-001');
    }

    public function test_search_by_tender_number_works(): void
    {
        $this->createBidRecord(['tender_number' => 'SEARCH-UNIQUE-99']);
        $this->createBidRecord(['tender_number' => 'OTHER-TENDER-01']);

        $this->actingAs($this->user)
            ->get(route('tenders.index', ['search' => 'UNIQUE-99']))
            ->assertOk()
            ->assertSee('SEARCH-UNIQUE-99')
            ->assertDontSee('OTHER-TENDER-01');
    }

    public function test_filter_by_country_works(): void
    {
        $this->createBidRecord([
            'country' => $this->country,
            'tender_number' => 'SA-TENDER-01',
        ]);
        $uae = Country::query()->where('name', 'United Arab Emirates')->firstOrFail();
        $this->createBidRecord([
            'country' => $uae,
            'tender_number' => 'UAE-TENDER-01',
        ]);

        $this->actingAs($this->user)
            ->get(route('tenders.index', ['country_id' => $this->country->id]))
            ->assertOk()
            ->assertSee('SA-TENDER-01')
            ->assertDontSee('UAE-TENDER-01');
    }

    public function test_filter_by_year_works(): void
    {
        $this->createBidRecord(['tender_number' => 'YEAR-2023', 'tender_year' => 2023]);
        $this->createBidRecord(['tender_number' => 'YEAR-2025', 'tender_year' => 2025]);

        $this->actingAs($this->user)
            ->get(route('tenders.index', ['year' => 2023]))
            ->assertOk()
            ->assertSee('YEAR-2023')
            ->assertDontSee('YEAR-2025');
    }

    public function test_filter_by_company_works(): void
    {
        $recordA = $this->createBidRecord([
            'company_name' => 'Tender Filter Co A',
            'tender_number' => 'CO-A-001',
        ]);
        $this->createBidRecord([
            'company_name' => 'Tender Filter Co B',
            'tender_number' => 'CO-B-001',
        ]);

        $this->actingAs($this->user)
            ->get(route('tenders.index', ['company_id' => $recordA->company_id]))
            ->assertOk()
            ->assertSee('CO-A-001')
            ->assertDontSee('CO-B-001');
    }

    public function test_tender_show_displays_kpis(): void
    {
        $record = $this->createBidRecord([
            'tender_number' => 'KPI-TENDER-01',
            'tender_title' => 'KPI Tender Profile',
            'bid_status' => 'awarded',
            'price_usd' => 300,
        ]);

        $this->actingAs($this->user)
            ->get(route('tenders.show', $record->tender))
            ->assertOk()
            ->assertSee('KPI-TENDER-01')
            ->assertSee('Bid Records')
            ->assertSee('Total Items')
            ->assertSee('Awarded Records');
    }

    public function test_tender_show_groups_companies(): void
    {
        $tender = Tender::query()->create([
            'tender_number' => 'GRP-CO-TND',
            'country_id' => $this->country->id,
            'year' => 2024,
            'version' => 'v1',
            'status' => 'active',
        ]);

        $this->createBidRecord([
            'tender' => $tender,
            'company_name' => 'Grouped Company One',
            'tender_number' => 'GRP-CO-TND',
        ]);
        $this->createBidRecord([
            'tender' => $tender,
            'company_name' => 'Grouped Company Two',
            'tender_number' => 'GRP-CO-TND',
        ]);

        $this->actingAs($this->user)
            ->get(route('tenders.show', $tender))
            ->assertOk()
            ->assertSee('Tender Company Summary')
            ->assertSee('Grouped Company One')
            ->assertSee('Grouped Company Two');
    }

    public function test_tender_show_groups_drugs(): void
    {
        $tender = Tender::query()->create([
            'tender_number' => 'GRP-DR-TND',
            'country_id' => $this->country->id,
            'year' => 2024,
            'version' => 'v1',
            'status' => 'active',
        ]);

        $this->createBidRecord([
            'tender' => $tender,
            'drug_display' => 'Grouped Drug Alpha',
            'tender_number' => 'GRP-DR-TND',
        ]);
        $this->createBidRecord([
            'tender' => $tender,
            'drug_display' => 'Grouped Drug Beta',
            'drug_code' => 'GRP-BETA-'.uniqid(),
            'tender_number' => 'GRP-DR-TND',
        ]);

        $this->actingAs($this->user)
            ->get(route('tenders.show', $tender))
            ->assertOk()
            ->assertSee('Tender Drug Summary')
            ->assertSee('Grouped Drug Alpha')
            ->assertSee('Grouped Drug Beta');
    }

    public function test_empty_state_when_no_tenders_exist(): void
    {
        $this->actingAs($this->user)
            ->get(route('tenders.index'))
            ->assertOk()
            ->assertSee('No tenders yet')
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

        $drug = StandardizedDrug::query()->create([
            'code' => $options['drug_code'] ?? 'TN-'.uniqid(),
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

        $tender = $options['tender'] ?? Tender::query()->create([
            'tender_number' => $options['tender_number'] ?? 'T-'.uniqid(),
            'country_id' => $country->id,
            'year' => $options['tender_year'] ?? 2024,
            'version' => $options['version'] ?? 'v1',
            'title' => $options['tender_title'] ?? null,
            'status' => 'active',
        ]);

        $item = TenderItem::query()->create([
            'tender_id' => $tender->id,
            'standardized_drug_id' => $drug->id,
            'description' => $options['product_description'] ?? 'Test product line',
        ]);

        $batch = ImportBatch::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'filename' => 'tender-test.csv',
            'original_filename' => 'tender-test.csv',
            'file_path' => 'imports/tender-test.csv',
            'file_hash' => hash('sha256', uniqid('', true)),
            'row_count' => 1,
            'status' => 'completed',
            'source_type' => 'csv',
        ]);

        $importRow = ImportRow::query()->create([
            'import_batch_id' => $batch->id,
            'row_number' => 1,
            'row_hash' => hash('sha256', uniqid('', true)),
            'raw_code' => $options['raw_code'] ?? $drug->code,
            'raw_inn' => $options['raw_inn'] ?? $drug->inn,
            'raw_product_name' => $options['raw_product_name'] ?? 'Raw product',
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
            'award_year' => $options['award_year'] ?? ($options['tender_year'] ?? 2024),
            'source_import_row_id' => $importRow->id,
            'import_batch_id' => $batch->id,
            'is_analytics_ready' => $options['is_analytics_ready'] ?? true,
            'excluded_from_stats' => $options['excluded_from_stats'] ?? false,
        ]);
    }
}
