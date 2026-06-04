<?php

namespace Tests\Feature;

use App\Enums\ImportRowValidationStatus;
use App\Enums\StandardizationStatus;
use App\Models\Company;
use App\Models\CompanyAlias;
use App\Models\Country;
use App\Models\DrugAlias;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\StandardizedDrug;
use App\Models\User;
use App\Services\Standardization\CountryStandardizationService;
use App\Services\Standardization\FuzzyMatcherService;
use App\Services\Standardization\ImportRowStandardizationService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\StandardizationReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandardizationEngineTest extends TestCase
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

    public function test_country_alias_uae_maps_to_united_arab_emirates(): void
    {
        $uae = Country::where('code', 'AE')->first();
        $row = $this->makeRow(['raw_country' => 'UAE']);

        $result = app(CountryStandardizationService::class)->standardize($row);

        $this->assertEquals($uae->id, $result['country_id']);
        $this->assertGreaterThanOrEqual(95.0, $result['confidence']);
        $this->assertEquals('United Arab Emirates', $result['normalized']['canonical_name']);
    }

    public function test_country_alias_ksa_maps_to_saudi_arabia(): void
    {
        $saudi = Country::where('code', 'SA')->first();
        $row = $this->makeRow(['raw_country' => 'KSA']);

        $result = app(CountryStandardizationService::class)->standardize($row);

        $this->assertEquals($saudi->id, $result['country_id']);
    }

    public function test_company_exact_alias_matching(): void
    {
        $company = Company::where('name', 'PharmaCorp International')->first();
        $row = $this->makeRow([
            'raw_country' => 'Saudi Arabia',
            'raw_company_name' => 'PharmaCorp',
            'raw_winner' => 'PharmaCorp',
        ]);

        $result = app(ImportRowStandardizationService::class)->standardizeRow($row);

        $row->refresh();

        $this->assertEquals($company->id, $row->company_id);
        $this->assertGreaterThanOrEqual(95.0, (float) $row->company_confidence);
    }

    public function test_drug_exact_code_matching(): void
    {
        $drug = StandardizedDrug::where('code', 'D001')->first();
        $row = $this->makeRow([
            'raw_code' => 'D001',
            'raw_inn' => 'Paracetamol',
            'raw_product_name' => 'Paracetamol 500mg',
            'raw_country' => 'Saudi Arabia',
            'raw_company_name' => 'PharmaCorp International',
            'raw_tender_number' => 'T-2024-001',
            'raw_year' => '2024',
        ]);

        app(ImportRowStandardizationService::class)->standardizeRow($row);
        $row->refresh();

        $this->assertEquals($drug->id, $row->standardized_drug_id);
        $this->assertEquals(95.0, (float) $row->drug_confidence);
    }

    public function test_fuzzy_matching_returns_expected_score_range(): void
    {
        $matcher = app(FuzzyMatcherService::class);
        $score = $matcher->similarity('paracetamol 500mg tablets', 'paracetamol 500mg tablet');

        $this->assertGreaterThanOrEqual(80, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_invalid_row_is_skipped_or_rejected(): void
    {
        $row = $this->makeRow([
            'validation_status' => ImportRowValidationStatus::Invalid->value,
            'raw_country' => null,
            'raw_year' => null,
            'normalized_data' => ['price_usd' => null],
        ]);

        $result = app(ImportRowStandardizationService::class)->standardizeRow($row);
        $row->refresh();

        $this->assertEquals(StandardizationStatus::Rejected->value, $row->standardization_status);
        $this->assertEquals('rejected', $result['status_bucket']);
    }

    public function test_valid_row_with_no_matches_becomes_review_required(): void
    {
        $row = $this->makeRow([
            'raw_code' => 'UNKNOWN99',
            'raw_inn' => 'Unknownium',
            'raw_product_name' => 'Unknownium 99mg',
            'raw_country' => 'Jordan',
            'raw_company_name' => 'NoSuch Pharma LLC',
            'raw_tender_number' => 'T-2099-001',
            'raw_year' => '2024',
        ]);

        app(ImportRowStandardizationService::class)->standardizeRow($row);
        $row->refresh();

        $this->assertEquals(StandardizationStatus::ReviewRequired->value, $row->standardization_status);
        $this->assertNull($row->standardized_drug_id);
        $this->assertNull($row->company_id);
    }

    public function test_existing_aliases_can_auto_approve_row(): void
    {
        $saudi = Country::where('code', 'SA')->first();

        $drug = StandardizedDrug::updateOrCreate(
            ['code' => 'D999'],
            [
                'inn' => 'testdrug',
                'display_name' => 'TestDrug 10mg',
                'product_name_normalized' => 'testdrug 10mg',
                'is_active' => true,
                'source' => 'test',
            ]
        );

        DrugAlias::updateOrCreate(
            [
                'standardized_drug_id' => $drug->id,
                'normalized_alias' => 'testdrug 10mg tabs',
            ],
            ['alias_value' => 'TestDrug 10mg tabs', 'source' => 'test']
        );

        $normalizer = app(\App\Support\Normalization\TextNormalizer::class);
        $companyNormalized = $normalizer->normalizeCompanyName('Exact Alias Pharma');

        $company = Company::updateOrCreate(
            ['normalized_name' => $companyNormalized],
            ['name' => 'Exact Alias Pharma', 'country_id' => $saudi->id, 'is_active' => true, 'source' => 'test']
        );

        CompanyAlias::updateOrCreate(
            [
                'company_id' => $company->id,
                'normalized_alias' => $companyNormalized,
            ],
            ['alias_value' => 'Exact Alias Pharma', 'source' => 'test']
        );

        $row = $this->makeRow([
            'raw_code' => 'D999',
            'raw_inn' => 'TestDrug',
            'raw_product_name' => 'TestDrug 10mg tabs',
            'raw_country' => 'Saudi Arabia',
            'raw_company_name' => 'Exact Alias Pharma',
            'raw_winner' => 'Exact Alias Pharma',
            'raw_tender_number' => 'T-2024-100',
            'raw_year' => '2024',
            'raw_version' => 'v1',
        ]);

        app(ImportRowStandardizationService::class)->standardizeRow($row);
        $row->refresh();

        $this->assertGreaterThanOrEqual(85, (float) $row->drug_confidence, 'drug confidence');
        $this->assertGreaterThanOrEqual(85, (float) $row->company_confidence, 'company confidence');
        $this->assertGreaterThanOrEqual(75, (float) $row->tender_confidence, 'tender confidence');
        $this->assertEquals(StandardizationStatus::AutoApproved->value, $row->standardization_status);
        $this->assertEquals($drug->id, $row->standardized_drug_id);
        $this->assertEquals($company->id, $row->company_id);
    }

    public function test_standardization_index_requires_auth(): void
    {
        $this->get(route('standardization.index'))->assertRedirect();
    }

    public function test_standardization_index_shows_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('standardization.index'))
            ->assertOk()
            ->assertSee('Product Matching')
            ->assertSee('Pending Review');
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
            'file_hash' => hash('sha256', 'test'),
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
            'raw_data' => [],
            'normalized_data' => [
                'price_usd' => 100.0,
                'year' => 2024,
            ],
        ], $overrides));
    }
}
