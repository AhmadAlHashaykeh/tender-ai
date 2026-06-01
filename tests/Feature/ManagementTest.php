<?php

namespace Tests\Feature;

use App\Models\BidRecord;
use App\Models\Company;
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

class ManagementTest extends TestCase
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

    public function test_management_page_lists_bid_records(): void
    {
        $record = $this->createBidRecord(['bid_status' => 'awarded']);

        $response = $this->actingAs($this->user)->get(route('management.index'));

        $response->assertOk();
        $response->assertSee((string) $record->id);
        $response->assertSee('Tender Data Management');
    }

    public function test_search_filter_works_by_drug_company_tender(): void
    {
        $this->createBidRecord([
            'drug_code' => 'SEARCH-001',
            'drug_inn' => 'Searchable Inn',
            'company_name' => 'Searchable Pharma Ltd',
            'tender_number' => 'SRCH-T-100',
        ]);
        $this->createBidRecord([
            'drug_code' => 'OTHER-999',
            'company_name' => 'Other Co',
            'tender_number' => 'OTHER-T-999',
        ]);

        $this->actingAs($this->user)
            ->get(route('management.index', ['search' => 'SEARCH-001']))
            ->assertOk()
            ->assertSee('SRCH-T-100')
            ->assertDontSee('OTHER-T-999');

        $this->actingAs($this->user)
            ->get(route('management.index', ['search' => 'Searchable Pharma']))
            ->assertOk()
            ->assertSee('SRCH-T-100');

        $this->actingAs($this->user)
            ->get(route('management.index', ['search' => 'SRCH-T-100']))
            ->assertOk()
            ->assertSee('SRCH-T-100');
    }

    public function test_country_filter_works(): void
    {
        $this->createBidRecord([
            'country' => $this->country,
            'tender_number' => 'CNTRY-FILTER-SAUDI-ONLY',
        ]);
        $uae = Country::query()->where('name', 'United Arab Emirates')->firstOrFail();
        $this->createBidRecord([
            'country' => $uae,
            'tender_number' => 'CNTRY-FILTER-UAE-ONLY',
        ]);

        $this->actingAs($this->user)
            ->get(route('management.index', ['country_id' => $this->country->id]))
            ->assertOk()
            ->assertSee('CNTRY-FILTER-SAUDI-ONLY')
            ->assertDontSee('CNTRY-FILTER-UAE-ONLY');
    }

    public function test_year_filter_works(): void
    {
        $this->createBidRecord([
            'award_year' => 2024,
            'tender_year' => 2024,
            'tender_number' => 'YEAR-FILTER-2024-ONLY',
        ]);
        $this->createBidRecord([
            'award_year' => 2022,
            'tender_year' => 2022,
            'tender_number' => 'YEAR-FILTER-2022-ONLY',
        ]);

        $this->actingAs($this->user)
            ->get(route('management.index', ['year' => 2024]))
            ->assertOk()
            ->assertSee('YEAR-FILTER-2024-ONLY')
            ->assertDontSee('YEAR-FILTER-2022-ONLY');
    }

    public function test_bid_status_filter_works(): void
    {
        $this->createBidRecord(['bid_status' => 'awarded', 'tender_number' => 'STATUS-FILTER-AWARDED-ONLY']);
        $this->createBidRecord(['bid_status' => 'lost', 'tender_number' => 'STATUS-FILTER-LOST-ONLY']);

        $this->actingAs($this->user)
            ->get(route('management.index', ['bid_status' => 'lost']))
            ->assertOk()
            ->assertSee('STATUS-FILTER-LOST-ONLY')
            ->assertDontSee('STATUS-FILTER-AWARDED-ONLY');
    }

    public function test_pagination_works(): void
    {
        foreach (range(1, 30) as $i) {
            $this->createBidRecord(['drug_code' => 'PAG-'.$i]);
        }

        $response = $this->actingAs($this->user)->get(route('management.index', ['per_page' => 25]));

        $response->assertOk();
        $response->assertSee('of 30 records');
    }

    public function test_edit_form_loads(): void
    {
        $record = $this->createBidRecord();

        $response = $this->actingAs($this->user)->get(route('management.bid-records.edit', $record));

        $response->assertOk();
        $response->assertSee('Edit Bid Record');
        $response->assertSee('Save Changes');
    }

    public function test_update_bid_record_safe_fields_works(): void
    {
        $record = $this->createBidRecord(['price_usd' => 10, 'bid_status' => 'participated']);

        $response = $this->actingAs($this->user)->put(route('management.bid-records.update', $record), [
            'price_usd' => 99.5,
            'original_awarded_price' => 88,
            'quantity' => 500,
            'tender_value' => 50000,
            'bid_status' => 'awarded',
            'is_winner' => '1',
            'is_analytics_ready' => '1',
            'excluded_from_stats' => '0',
            'exclusion_reason' => null,
        ]);

        $response->assertRedirect(route('management.bid-records.edit', $record));
        $record->refresh();
        $this->assertEquals(99.5, (float) $record->price_usd);
        $this->assertEquals('awarded', $record->bid_status);
        $this->assertTrue($record->is_winner);
        $this->assertArrayHasKey('edited_at', $record->metadata ?? []);
    }

    public function test_raw_import_row_data_is_not_changed_by_edit(): void
    {
        $record = $this->createBidRecord([
            'raw_code' => 'RAW-KEEP-01',
            'raw_inn' => 'Raw Inn Value',
            'raw_product_name' => 'Raw Product Name',
        ]);

        $this->actingAs($this->user)->put(route('management.bid-records.update', $record), [
            'price_usd' => 250,
            'original_awarded_price' => 240,
            'quantity' => 10,
            'tender_value' => 2500,
            'bid_status' => 'lost',
            'is_winner' => '0',
            'is_analytics_ready' => '0',
            'excluded_from_stats' => '0',
        ]);

        $row = $record->sourceImportRow()->first();
        $this->assertNotNull($row);
        $this->assertEquals('RAW-KEEP-01', $row->raw_code);
        $this->assertEquals('Raw Inn Value', $row->raw_inn);
        $this->assertEquals('Raw Product Name', $row->raw_product_name);
    }

    public function test_toggle_excluded_from_stats_works(): void
    {
        $record = $this->createBidRecord(['excluded_from_stats' => false]);

        $this->actingAs($this->user)
            ->post(route('management.bid-records.toggle-exclusion', $record))
            ->assertRedirect();

        $record->refresh();
        $this->assertTrue($record->excluded_from_stats);

        $this->actingAs($this->user)
            ->post(route('management.bid-records.toggle-exclusion', $record))
            ->assertRedirect();

        $record->refresh();
        $this->assertFalse($record->excluded_from_stats);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function createBidRecord(array $options = []): BidRecord
    {
        $country = $options['country'] ?? $this->country;

        $drug = StandardizedDrug::query()->create([
            'code' => $options['drug_code'] ?? 'MG-'.uniqid(),
            'inn' => $options['drug_inn'] ?? 'Test Inn',
            'display_name' => $options['drug_display'] ?? 'Test Drug',
            'is_active' => true,
            'source' => 'test',
        ]);

        $company = Company::query()->create([
            'name' => $options['company_name'] ?? 'Test Company',
            'normalized_name' => strtolower($options['company_name'] ?? 'test company'),
            'is_active' => true,
            'source' => 'test',
        ]);

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
            'filename' => 'mgmt-test.csv',
            'original_filename' => 'mgmt-test.csv',
            'file_path' => 'imports/mgmt-test.csv',
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
