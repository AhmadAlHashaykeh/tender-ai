<?php

namespace Tests\Feature;

use App\Enums\ImportRowValidationStatus;
use App\Enums\StandardizationStatus;
use App\Models\BidRecord;
use App\Models\Country;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Region;
use App\Models\StandardizedDrug;
use App\Services\Import\ImportCountryRepairService;
use App\Services\Materialization\ImportMaterializationService;
use App\Services\Materialization\MaterializationEligibilityService;
use App\Services\Standardization\CountryStandardizationService;
use App\Services\Standardization\ImportRowStandardizationService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\StandardizationReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CountryStandardizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RegionSeeder::class,
            CurrencySeeder::class,
            CountrySeeder::class,
            StandardizationReferenceSeeder::class,
        ]);
    }

    public function test_ksa_maps_to_saudi_arabia(): void
    {
        $saudi = Country::where('code', 'SA')->firstOrFail();
        $result = $this->standardizeRawCountry('KSA');

        $this->assertEquals($saudi->id, $result['country_id']);
        $this->assertGreaterThanOrEqual(95.0, $result['confidence']);
    }

    public function test_saudi_maps_to_saudi_arabia(): void
    {
        $saudi = Country::where('code', 'SA')->firstOrFail();
        $result = $this->standardizeRawCountry('Saudi');

        $this->assertEquals($saudi->id, $result['country_id']);
    }

    public function test_oman_maps_to_oman(): void
    {
        $oman = Country::where('code', 'OM')->firstOrFail();
        $result = $this->standardizeRawCountry('Oman');

        $this->assertEquals($oman->id, $result['country_id']);
    }

    public function test_uae_maps_to_united_arab_emirates(): void
    {
        $uae = Country::where('code', 'AE')->firstOrFail();
        $result = $this->standardizeRawCountry('UAE');

        $this->assertEquals($uae->id, $result['country_id']);
    }

    public function test_gcc_maps_to_region_without_fake_country(): void
    {
        $gcc = Region::where('code', 'GCC')->firstOrFail();
        $result = $this->standardizeRawCountry('GCC');

        $this->assertNull($result['country_id']);
        $this->assertEquals($gcc->id, $result['region_id']);
        $this->assertSame('region_only', $result['match_type']);
        $this->assertTrue($result['review_required']);
    }

    public function test_ghc_maps_to_gcc_region(): void
    {
        $gcc = Region::where('code', 'GCC')->firstOrFail();
        $result = $this->standardizeRawCountry('GHC');

        $this->assertNull($result['country_id']);
        $this->assertEquals($gcc->id, $result['region_id']);
    }

    public function test_gcc_materialization_requires_specific_country(): void
    {
        $row = $this->makeApprovedRowWithoutCountry('GCC', [
            'region_id' => Region::where('code', 'GCC')->value('id'),
        ]);

        $this->assertSame(
            MaterializationEligibilityService::REASON_REGION_REQUIRES_COUNTRY,
            app(MaterializationEligibilityService::class)->ineligibilityReason($row),
        );
    }

    public function test_repair_command_updates_country_id_without_reupload(): void
    {
        $saudi = Country::where('code', 'SA')->firstOrFail();
        $drug = StandardizedDrug::where('code', 'D001')->firstOrFail();
        $row = $this->makeApprovedRowWithoutCountry('KSA');
        $row->update(['standardized_drug_id' => $drug->id]);

        $summary = app(ImportCountryRepairService::class)->repairBatch($row->importBatch);
        $row->refresh();

        $this->assertGreaterThanOrEqual(1, $summary['processed']);
        $this->assertEquals($saudi->id, $row->normalized_data['country_id']);
        $this->assertEquals($drug->id, $row->standardized_drug_id);
    }

    public function test_materialization_works_after_country_repair(): void
    {
        $row = $this->makeApprovedRowWithoutCountry('Oman');
        $batch = $row->importBatch;

        $normalized = $row->normalized_data ?? [];
        $normalized['materialization_skip_reason'] = MaterializationEligibilityService::REASON_MISSING_COUNTRY;
        $normalized['materialization_status'] = 'skipped';
        $row->update(['normalized_data' => $normalized]);

        app(ImportCountryRepairService::class)->repairBatch($batch);
        $row->refresh();

        $this->assertNotNull($row->normalized_data['country_id'] ?? null);
        $this->assertArrayNotHasKey('materialization_skip_reason', $row->normalized_data ?? []);

        $outcome = app(ImportMaterializationService::class)->materializeRow($row->fresh());
        $this->assertSame('materialized', $outcome['bucket']);
        $row->refresh();
        $this->assertNotNull($row->bid_record_id);
        $this->assertEquals(1, BidRecord::query()->where('import_batch_id', $batch->id)->count());
    }

    public function test_diagnose_countries_command_runs(): void
    {
        $row = $this->makeApprovedRowWithoutCountry('KSA');
        Artisan::call('imports:diagnose-countries', ['batch' => $row->import_batch_id]);
        $output = Artisan::output();

        $this->assertStringContainsString('country diagnostics', strtolower($output));
        $this->assertStringContainsString('KSA', $output);
    }

    public function test_repair_countries_artisan_command(): void
    {
        $row = $this->makeApprovedRowWithoutCountry('KSA');
        Artisan::call('imports:repair-countries', ['--batch' => $row->import_batch_id]);

        $row->refresh();
        $this->assertNotNull($row->normalized_data['country_id'] ?? null);
        $this->assertStringContainsString('Country mapped', Artisan::output());
    }

    /**
     * @return array<string, mixed>
     */
    protected function standardizeRawCountry(string $rawCountry): array
    {
        $row = ImportRow::create([
            'import_batch_id' => ImportBatch::create([
                'uuid' => (string) str()->uuid(),
                'filename' => 'country.csv',
                'original_filename' => 'country.csv',
                'file_path' => 'imports/country.csv',
                'file_hash' => hash('sha256', uniqid('', true)),
                'row_count' => 1,
                'status' => 'completed',
                'source_type' => 'csv',
            ])->id,
            'row_number' => 1,
            'row_hash' => hash('sha256', uniqid('', true)),
            'raw_country' => $rawCountry,
            'raw_code' => 'D001',
            'raw_year' => '2024',
            'validation_status' => ImportRowValidationStatus::Valid->value,
            'standardization_status' => StandardizationStatus::Pending->value,
            'raw_data' => [],
            'normalized_data' => ['price_usd' => 10, 'year' => 2024],
        ]);

        return app(CountryStandardizationService::class)->standardize($row);
    }

    /**
     * @param  array<string, mixed>  $normalizedExtras
     */
    protected function makeApprovedRowWithoutCountry(string $rawCountry, array $normalizedExtras = []): ImportRow
    {
        $batch = ImportBatch::create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'repair.csv',
            'original_filename' => 'repair.csv',
            'file_path' => 'imports/repair.csv',
            'file_hash' => hash('sha256', uniqid('', true)),
            'row_count' => 1,
            'status' => 'completed',
            'source_type' => 'csv',
        ]);

        return ImportRow::create([
            'import_batch_id' => $batch->id,
            'row_number' => 1,
            'row_hash' => hash('sha256', uniqid('', true)),
            'raw_code' => 'D001',
            'raw_inn' => 'Paracetamol',
            'raw_product_name' => 'Paracetamol 500mg',
            'raw_country' => $rawCountry,
            'raw_company_name' => 'PharmaCorp International',
            'raw_winner' => 'PharmaCorp',
            'raw_tender_number' => 'T-2024-001',
            'raw_year' => '2024',
            'raw_price_usd' => '425',
            'validation_status' => ImportRowValidationStatus::Valid->value,
            'standardization_status' => StandardizationStatus::Approved->value,
            'row_type' => 'winning_bid',
            'raw_data' => [],
            'normalized_data' => array_merge([
                'price_usd' => 425.0,
                'year' => 2024,
                'country_id' => null,
            ], $normalizedExtras),
        ]);
    }
}
