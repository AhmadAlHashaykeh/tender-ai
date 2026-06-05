<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportRowValidationStatus;
use App\Models\BidRecord;
use App\Models\Country;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Tender;
use App\Models\User;
use App\Services\Import\ImportValidatorService;
use App\Services\Import\ManualImportService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UploadDataEntryHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RegionSeeder::class,
            CurrencySeeder::class,
            CountrySeeder::class,
        ]);
    }

    public function test_upload_page_shows_all_three_sections(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('uploads.index'))
            ->assertOk()
            ->assertSee('1. Upload Historical Excel Data', false)
            ->assertSee('2. Add Historical Row Manually', false)
            ->assertSee('3. Add Upcoming Tender', false)
            ->assertSee('Required column headers', false)
            ->assertSee('Price USD', false)
            ->assertSee('do not create bid records', false);
    }

    public function test_excel_header_validation_rejects_missing_required_headers(): void
    {
        $user = User::factory()->create();
        $csv = "Code,INN,Product Name,Country,Tender #,Awarded price,Winner,Company Name,Version,Year,Qty,Tender Value\n";
        $csv .= "D001,Test,Test Product,Saudi Arabia,T-1,10,,Co,v1,2024,1,10\n";

        $file = UploadedFile::fake()->createWithContent('bad_headers.csv', $csv);

        $response = $this->actingAs($user)->post(route('uploads.store'), ['file' => $file]);

        $response->assertRedirect(route('uploads.index'));
        $response->assertSessionHasErrors('file');

        $batch = ImportBatch::query()->latest()->first();
        $this->assertNotNull($batch);
        $this->assertEquals(ImportBatchStatus::Failed->value, $batch->status);
        $this->assertEquals(0, $batch->row_count);
        $this->assertContains('Price USD', $batch->metadata['missing_headers'] ?? []);
        $this->assertEquals(0, ImportRow::query()->where('import_batch_id', $batch->id)->count());
    }

    public function test_manual_historical_entry_creates_import_batch_and_row(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('uploads.manual.store'), [
            'code' => 'TEST001',
            'inn' => 'Paracetamol',
            'product_name' => 'Paracetamol 500mg',
            'country' => 'Saudi Arabia',
            'tender_number' => 'T-MAN-001',
            'awarded_price' => 420,
            'price_usd' => 425,
            'winner' => 'Test Pharma',
            'company_name' => 'Test Pharma International',
            'version' => 'v1',
            'year' => 2024,
            'qty' => 1000,
            'tender_value' => 425000,
        ]);

        $batch = ImportBatch::query()->latest()->first();
        $response->assertRedirect(route('imports.show', $batch));

        $this->assertEquals('manual', $batch->source_type);
        $this->assertEquals(1, $batch->row_count);

        $row = ImportRow::query()->where('import_batch_id', $batch->id)->first();
        $this->assertNotNull($row);
        $this->assertEquals('TEST001', $row->raw_code);
        $this->assertEquals('winning_bid', $row->row_type);
        $this->assertEquals('pending', $row->standardization_status);
        $this->assertIsArray($row->raw_data);
        $this->assertEquals('TEST001', $row->raw_data['canonical']['code'] ?? null);
    }

    public function test_manual_historical_entry_uses_same_validation_logic(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('uploads.manual.store'), [
            'inn' => 'Drug X',
            'product_name' => 'Drug X 10mg',
            'country' => 'Saudi Arabia',
            'price_usd' => 99.5,
            'year' => 2024,
        ]);

        $row = ImportRow::query()->latest()->first();
        $validator = app(ImportValidatorService::class);
        $expected = $validator->validate([
            'code' => null,
            'inn' => 'Drug X',
            'product_name' => 'Drug X 10mg',
            'country' => 'Saudi Arabia',
            'tender_number' => null,
            'awarded_price' => null,
            'price_usd' => '99.5',
            'winner' => null,
            'company_name' => null,
            'version' => null,
            'year' => '2024',
            'qty' => null,
            'tender_value' => null,
        ]);

        $this->assertEquals($expected['validation_status'], $row->validation_status);
        $this->assertEquals($expected['normalized_data']['price_usd'], $row->normalized_data['price_usd']);
    }

    public function test_manual_invalid_row_is_stored_but_marked_invalid(): void
    {
        $user = User::factory()->create();

        $batch = app(ManualImportService::class)->store($user, [
            'inn' => 'Bad Row Drug',
            'product_name' => 'Bad Row',
            'country' => 'Saudi Arabia',
            'price_usd' => '0',
            'year' => '2024',
        ]);

        $row = $batch->importRows()->first();
        $this->assertNotNull($row);
        $this->assertEquals(ImportRowValidationStatus::Invalid->value, $row->validation_status);
        $this->assertNotNull($row->error_message);
    }

    public function test_upcoming_tender_creates_tender_and_no_bid_record(): void
    {
        $user = User::factory()->create();
        $beforeBids = BidRecord::query()->count();

        $this->actingAs($user)->post(route('uploads.upcoming-tenders.store'), [
            'tender_name' => 'MOH Q3 Oncology Tender',
            'tender_number' => 'NPT 99/26',
            'country' => 'Saudi Arabia',
            'year' => 2026,
            'version' => 'V1',
            'expected_inn' => 'Abiraterone Acetate',
            'expected_product_name' => 'Abiraterone 250mg Tablet',
            'expected_qty' => 50000,
            'notes' => 'Planned bid',
        ])->assertRedirect(route('uploads.index'));

        $tender = Tender::query()->latest()->first();
        $this->assertEquals('upcoming', $tender->status);
        $this->assertEquals('MOH Q3 Oncology Tender', $tender->title);
        $this->assertEquals($beforeBids, BidRecord::query()->count());
        $this->assertGreaterThan(0, $tender->tenderItems()->count());
    }

    public function test_upcoming_tender_appears_in_ai_recommendation_program_dropdown(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('uploads.upcoming-tenders.store'), [
            'tender_name' => 'Unique Upcoming Tender XYZ',
            'tender_number' => 'UP-XYZ-2026',
            'country' => 'KSA',
            'year' => 2026,
            'expected_code' => 'UPCODE1',
            'expected_product_name' => 'Future Drug',
        ]);

        $this->actingAs($user)
            ->get(route('ai.recommendations.create'))
            ->assertOk()
            ->assertSee('Unique Upcoming', false)
            ->assertSee('Select tender program...', false);
    }
}
