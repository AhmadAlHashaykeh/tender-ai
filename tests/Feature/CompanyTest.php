<?php

namespace Tests\Feature;

use App\Models\BidRecord;
use App\Models\Company;
use App\Models\CompanyAlias;
use App\Models\Country;
use App\Models\Currency;
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

class CompanyTest extends TestCase
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

    public function test_companies_index_lists_real_companies(): void
    {
        $company = $this->createBidRecord(['company_name' => 'Acme Pharma Ltd'])->company;

        $this->actingAs($this->user)
            ->get(route('companies.index'))
            ->assertOk()
            ->assertSee('Company Intelligence')
            ->assertSee('Acme Pharma Ltd');
    }

    public function test_company_metrics_include_bid_record_count(): void
    {
        $company = Company::query()->create([
            'name' => 'Metrics Test Co',
            'normalized_name' => 'metrics test co',
            'is_active' => true,
            'source' => 'test',
        ]);

        $this->createBidRecord(['company_name' => 'Metrics Test Co', 'tender_number' => 'MT-001']);
        $this->createBidRecord(['company_name' => 'Metrics Test Co', 'tender_number' => 'MT-002']);

        $this->actingAs($this->user)
            ->get(route('companies.index'))
            ->assertOk()
            ->assertSee('Metrics Test Co')
            ->assertSee('2');
    }

    public function test_search_by_company_name_works(): void
    {
        $this->createBidRecord(['company_name' => 'Unique Searchable Pharma']);
        $this->createBidRecord(['company_name' => 'Other Vendor Inc']);

        $this->actingAs($this->user)
            ->get(route('companies.index', ['search' => 'Searchable']))
            ->assertOk()
            ->assertSee('Unique Searchable Pharma')
            ->assertDontSee('Other Vendor Inc');
    }

    public function test_search_by_company_alias_works(): void
    {
        $record = $this->createBidRecord(['company_name' => 'Alias Parent Co']);
        CompanyAlias::query()->create([
            'company_id' => $record->company_id,
            'alias_value' => 'Known Alias Label',
            'normalized_alias' => 'known alias label',
            'alias_type' => 'legal_name',
            'source' => 'test',
        ]);

        $this->actingAs($this->user)
            ->get(route('companies.index', ['search' => 'Known Alias']))
            ->assertOk()
            ->assertSee('Alias Parent Co');
    }

    public function test_filter_by_country_works(): void
    {
        $this->createBidRecord([
            'country' => $this->country,
            'company_name' => 'Saudi Only Co',
            'tender_number' => 'SA-ONLY',
        ]);
        $uae = Country::query()->where('name', 'United Arab Emirates')->firstOrFail();
        $this->createBidRecord([
            'country' => $uae,
            'company_name' => 'UAE Only Co',
            'tender_number' => 'UAE-ONLY',
        ]);

        $this->actingAs($this->user)
            ->get(route('companies.index', ['country_id' => $this->country->id]))
            ->assertOk()
            ->assertSee('Saudi Only Co')
            ->assertDontSee('UAE Only Co');
    }

    public function test_filter_by_bid_status_works(): void
    {
        $this->createBidRecord([
            'company_name' => 'Awarded Only Co',
            'bid_status' => 'awarded',
            'tender_number' => 'AWD-1',
        ]);
        $this->createBidRecord([
            'company_name' => 'Lost Only Co',
            'bid_status' => 'lost',
            'is_winner' => false,
            'tender_number' => 'LST-1',
        ]);

        $this->actingAs($this->user)
            ->get(route('companies.index', ['bid_status' => 'lost']))
            ->assertOk()
            ->assertSee('Lost Only Co')
            ->assertDontSee('Awarded Only Co');
    }

    public function test_pagination_works(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $label = sprintf('Paginate Co %03d', $i);
            Company::query()->create([
                'name' => $label,
                'normalized_name' => strtolower($label),
                'is_active' => true,
                'source' => 'test',
            ]);
        }

        $this->actingAs($this->user)
            ->get(route('companies.index', ['per_page' => 25]))
            ->assertOk()
            ->assertSee('Paginate Co 001')
            ->assertSee('Paginate Co 025')
            ->assertDontSee('Paginate Co 030');

        $this->actingAs($this->user)
            ->get(route('companies.index', ['per_page' => 25, 'page' => 2]))
            ->assertOk()
            ->assertSee('Paginate Co 030');
    }

    public function test_company_show_page_displays_kpis(): void
    {
        $record = $this->createBidRecord([
            'company_name' => 'Profile KPI Co',
            'bid_status' => 'awarded',
            'price_usd' => 250,
            'tender_value' => 50000,
        ]);

        $this->actingAs($this->user)
            ->get(route('companies.show', $record->company))
            ->assertOk()
            ->assertSee('Profile KPI Co')
            ->assertSee('Bid Records')
            ->assertSee('Awarded Wins')
            ->assertSee('Win Rate');
    }

    public function test_company_show_groups_bid_records_under_one_company(): void
    {
        $company = Company::query()->create([
            'name' => 'Grouped History Co',
            'normalized_name' => 'grouped history co',
            'is_active' => true,
            'source' => 'test',
        ]);

        $this->createBidRecord(['company_name' => 'Grouped History Co', 'tender_number' => 'GRP-001']);
        $this->createBidRecord(['company_name' => 'Grouped History Co', 'tender_number' => 'GRP-002']);

        $this->actingAs($this->user)
            ->get(route('companies.show', $company))
            ->assertOk()
            ->assertSee('GRP-001')
            ->assertSee('GRP-002')
            ->assertSee('Tender / Bid History');
    }

    public function test_drug_summary_appears_on_profile(): void
    {
        $record = $this->createBidRecord([
            'company_name' => 'Drug Summary Co',
            'drug_display' => 'Summary Drug Alpha',
        ]);

        $this->actingAs($this->user)
            ->get(route('companies.show', $record->company))
            ->assertOk()
            ->assertSee('Company Drug Summary')
            ->assertSee('Summary Drug Alpha');
    }

    public function test_country_summary_appears_on_profile(): void
    {
        $record = $this->createBidRecord([
            'company_name' => 'Country Summary Co',
            'country' => $this->country,
        ]);

        $this->actingAs($this->user)
            ->get(route('companies.show', $record->company))
            ->assertOk()
            ->assertSee('Company Country Summary')
            ->assertSee('Saudi Arabia');
    }

    public function test_ai_summary_sections_are_not_present(): void
    {
        $record = $this->createBidRecord(['company_name' => 'No AI Co']);

        $this->actingAs($this->user)
            ->get(route('companies.show', $record->company))
            ->assertOk()
            ->assertDontSee('AI-generated')
            ->assertDontSee('Company Intelligence Summary')
            ->assertDontSee('market fit score')
            ->assertDontSee('AI recommendations');
    }

    public function test_empty_state_when_no_companies_exist(): void
    {
        $this->actingAs($this->user)
            ->get(route('companies.index'))
            ->assertOk()
            ->assertSee('No companies yet')
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
            'code' => $options['drug_code'] ?? 'CO-'.uniqid(),
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
            'filename' => 'company-test.csv',
            'original_filename' => 'company-test.csv',
            'file_path' => 'imports/company-test.csv',
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
            'award_year' => $options['award_year'] ?? 2024,
            'source_import_row_id' => $importRow->id,
            'import_batch_id' => $batch->id,
            'is_analytics_ready' => $options['is_analytics_ready'] ?? true,
            'excluded_from_stats' => $options['excluded_from_stats'] ?? false,
        ]);
    }
}
