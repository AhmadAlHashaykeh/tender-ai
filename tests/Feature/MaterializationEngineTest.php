<?php

namespace Tests\Feature;

use App\Enums\ImportRowValidationStatus;
use App\Enums\StandardizationStatus;
use App\Models\BidRecord;
use App\Models\Company;
use App\Models\CompanyAlias;
use App\Models\Country;
use App\Models\DrugAlias;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Models\TenderItem;
use App\Models\User;
use App\Services\Materialization\ImportMaterializationService;
use App\Services\Standardization\ImportRowStandardizationService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\StandardizationReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterializationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected ImportMaterializationService $materialization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RegionSeeder::class,
            CurrencySeeder::class,
            CountrySeeder::class,
            StandardizationReferenceSeeder::class,
        ]);

        $this->materialization = app(ImportMaterializationService::class);
    }

    public function test_auto_approved_row_creates_domain_entities(): void
    {
        $row = $this->makeAutoApprovedRow();

        $beforeCompanies = Company::count();
        $beforeDrugs = StandardizedDrug::count();
        $beforeTenders = Tender::count();

        $outcome = $this->materialization->materializeRow($row);
        $row->refresh();

        $this->assertEquals('materialized', $outcome['bucket']);
        $this->assertNotNull($row->company_id);
        $this->assertNotNull($row->standardized_drug_id);
        $this->assertNotNull($row->tender_id);
        $this->assertNotNull($row->tender_item_id);
        $this->assertNotNull($row->bid_record_id);

        $this->assertGreaterThanOrEqual($beforeCompanies, Company::count());
        $this->assertGreaterThanOrEqual($beforeDrugs, StandardizedDrug::count());
        $this->assertGreaterThanOrEqual($beforeTenders, Tender::count());

        $this->assertDatabaseHas('tender_items', [
            'id' => $row->tender_item_id,
            'source_import_row_id' => $row->id,
        ]);

        $bid = BidRecord::find($row->bid_record_id);
        $this->assertNotNull($bid);
        $this->assertEquals('awarded', $bid->bid_status);
        $this->assertTrue($bid->is_winner);
        $this->assertEquals('winning_bid', $bid->row_type);
        $this->assertTrue($bid->is_analytics_ready);
        $this->assertEquals($row->id, $bid->source_import_row_id);
        $this->assertEquals('materialized', $row->normalized_data['materialization_status']);
    }

    public function test_materializing_same_row_twice_does_not_duplicate_bid_records(): void
    {
        $row = $this->makeAutoApprovedRow();

        $this->materialization->materializeRow($row);
        $row->refresh();
        $firstBidId = $row->bid_record_id;

        $this->materialization->materializeRow($row);
        $row->refresh();

        $this->assertEquals($firstBidId, $row->bid_record_id);
        $this->assertEquals(1, BidRecord::where('source_import_row_id', $row->id)->count());
        $this->assertEquals(1, TenderItem::where('source_import_row_id', $row->id)->count());
    }

    public function test_review_required_row_is_not_materialized(): void
    {
        $row = $this->makeAutoApprovedRow();
        $row->update(['standardization_status' => StandardizationStatus::ReviewRequired->value]);

        $outcome = $this->materialization->materializeRow($row);
        $row->refresh();

        $this->assertEquals('skipped', $outcome['bucket']);
        $this->assertNull($row->bid_record_id);
        $this->assertEquals(0, BidRecord::where('source_import_row_id', $row->id)->count());
    }

    public function test_rejected_row_is_not_materialized(): void
    {
        $row = $this->makeRow([
            'validation_status' => ImportRowValidationStatus::Invalid->value,
            'standardization_status' => StandardizationStatus::Rejected->value,
            'normalized_data' => ['price_usd' => null],
        ]);

        $outcome = $this->materialization->materializeRow($row);

        $this->assertEquals('skipped', $outcome['bucket']);
        $this->assertEquals(0, BidRecord::count());
    }

    public function test_aliases_are_created_or_usage_count_incremented(): void
    {
        $row = $this->makeAutoApprovedRow();
        $normalized = app(\App\Support\Normalization\TextNormalizer::class)
            ->normalizeCompanyName('PharmaCorp International');

        CompanyAlias::query()->create([
            'company_id' => Company::first()->id,
            'alias_value' => 'PharmaCorp International',
            'normalized_alias' => $normalized,
            'alias_type' => 'company_name',
            'source' => 'seed',
            'usage_count' => 2,
        ]);

        $this->materialization->materializeRow($row);

        $alias = CompanyAlias::query()
            ->where('normalized_alias', $normalized)
            ->first();

        $this->assertNotNull($alias);
        $this->assertGreaterThanOrEqual(3, $alias->usage_count);

        $drug = StandardizedDrug::where('code', 'D001')->first();
        $this->assertNotNull(
            DrugAlias::query()->where('standardized_drug_id', $drug->id)->where('alias_type', 'product_name')->first()
        );
    }

    public function test_import_batch_materialize_route_dispatches_background_job(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $user = User::factory()->create();
        $batch = $this->makeAutoApprovedRow()->importBatch;

        $response = $this->actingAs($user)->post(route('imports.materialize', $batch));

        $response->assertRedirect(route('imports.show', $batch));
        $response->assertSessionHas('success', 'Materialization has started in the background.');

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\MaterializeImportBatchJob::class);
    }

    protected function makeAutoApprovedRow(): ImportRow
    {
        $row = $this->makeRow([
            'raw_code' => 'D001',
            'raw_inn' => 'Paracetamol',
            'raw_product_name' => 'Paracetamol 500mg',
            'raw_country' => 'Saudi Arabia',
            'raw_company_name' => 'PharmaCorp International',
            'raw_winner' => 'PharmaCorp',
            'raw_tender_number' => 'T-2024-001',
            'raw_year' => '2024',
            'raw_version' => 'v1',
            'raw_price_usd' => '425',
            'raw_awarded_price' => '420',
            'raw_qty' => '1000',
            'raw_tender_value' => '425000',
            'normalized_data' => [
                'price_usd' => 425.0,
                'awarded_price' => 420.0,
                'qty' => 1000.0,
                'tender_value' => 425000.0,
                'year' => 2024,
            ],
        ]);

        app(ImportRowStandardizationService::class)->standardizeRow($row);
        $row->refresh();

        $this->assertEquals(
            StandardizationStatus::AutoApproved->value,
            $row->standardization_status,
            'Row must be auto_approved before materialization test.'
        );

        return $row;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeRow(array $overrides = []): ImportRow
    {
        $batch = ImportBatch::create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'test.csv',
            'original_filename' => 'test.csv',
            'file_path' => 'imports/test.csv',
            'file_hash' => hash('sha256', uniqid('', true)),
            'row_count' => 1,
            'status' => 'completed',
            'source_type' => 'csv',
        ]);

        return ImportRow::create(array_merge([
            'import_batch_id' => $batch->id,
            'row_number' => 1,
            'row_hash' => hash('sha256', uniqid('', true)),
            'raw_code' => 'D001',
            'raw_inn' => 'Paracetamol',
            'raw_product_name' => 'Paracetamol 500mg',
            'raw_country' => 'UAE',
            'raw_tender_number' => 'T-2024-002',
            'raw_awarded_price' => '100',
            'raw_price_usd' => '100',
            'raw_winner' => 'PharmaCorp',
            'raw_company_name' => 'PharmaCorp International',
            'raw_version' => 'v1',
            'raw_year' => '2024',
            'raw_qty' => '10',
            'raw_tender_value' => '1000',
            'validation_status' => ImportRowValidationStatus::Valid->value,
            'standardization_status' => StandardizationStatus::Pending->value,
            'row_type' => 'winning_bid',
            'raw_data' => [],
            'normalized_data' => [
                'price_usd' => 100.0,
                'year' => 2024,
            ],
        ], $overrides));
    }
}
