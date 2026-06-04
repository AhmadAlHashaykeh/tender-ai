<?php

namespace Tests\Feature;

use App\Enums\ImportRowValidationStatus;
use App\Enums\StandardizationStatus;
use App\Models\BidRecord;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Services\Import\ImportPipelineOrchestratorService;
use App\Services\Import\ImportPipelineReadinessService;
use App\Services\Materialization\ImportMaterializationService;
use App\Services\Materialization\MaterializationEligibilityService;
use App\Services\Standardization\ImportRowStandardizationService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\StandardizationReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MaterializationEligibilityTest extends TestCase
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

    public function test_approved_row_creates_bid_record_and_records_no_skip_reason(): void
    {
        $row = $this->makeAutoApprovedRow();

        $outcome = app(ImportMaterializationService::class)->materializeRow($row);

        $this->assertSame('materialized', $outcome['bucket']);
        $row->refresh();
        $this->assertNotNull($row->bid_record_id);
        $this->assertArrayNotHasKey('materialization_skip_reason', $row->normalized_data ?? []);
    }

    public function test_ineligible_row_stores_skip_reason(): void
    {
        $row = $this->makeAutoApprovedRow();
        $normalized = $row->normalized_data ?? [];
        $normalized['price_usd'] = 0;
        $row->update([
            'standardization_status' => StandardizationStatus::Approved->value,
            'normalized_data' => $normalized,
        ]);

        $outcome = app(ImportMaterializationService::class)->materializeRow($row->fresh());

        $this->assertSame('skipped', $outcome['bucket']);
        $this->assertSame(MaterializationEligibilityService::REASON_INVALID_PRICE_USD, $outcome['skip_reason']);
        $row->refresh();
        $this->assertSame(MaterializationEligibilityService::REASON_INVALID_PRICE_USD, $row->normalized_data['materialization_skip_reason']);
    }

    public function test_whitespace_tender_number_is_not_eligible(): void
    {
        $eligibility = app(MaterializationEligibilityService::class);

        $country = \App\Models\Country::query()->firstOrFail();

        $row = $this->makeAutoApprovedRow([
            'raw_tender_number' => '   ',
            'normalized_data' => [
                'price_usd' => 100,
                'country_id' => $country->id,
                'standardization' => ['tender' => ['tender_number' => '   ']],
            ],
        ]);

        $this->assertSame(
            MaterializationEligibilityService::REASON_MISSING_TENDER_NUMBER,
            $eligibility->ineligibilityReason($row->fresh()),
        );
    }

    public function test_pipeline_not_ready_when_eligible_rows_pending(): void
    {
        $row = $this->makeAutoApprovedRow();
        $batch = $row->importBatch;

        $batch->update([
            'metadata' => [
                'pipeline_status' => 'ready',
                'pipeline_ready_at' => now()->toIso8601String(),
                'materialization_status' => 'completed',
                'statistics_status' => 'completed',
            ],
        ]);

        $this->assertFalse(app(ImportPipelineReadinessService::class)->batchIsPipelineReady($batch->fresh()));
    }

    public function test_orchestrator_does_not_mark_ready_with_pending_eligible_rows(): void
    {
        $row = $this->makeAutoApprovedRow();
        $batch = $row->importBatch;
        $batch->update([
            'metadata' => [
                'materialization_status' => 'completed',
                'standardization_status' => 'completed',
            ],
        ]);

        app(ImportPipelineOrchestratorService::class)->onMaterializationComplete($batch->fresh());

        $batch->refresh();
        $this->assertNotSame('ready', $batch->metadata['pipeline_status'] ?? null);
        $this->assertNull($batch->metadata['pipeline_ready_at'] ?? null);
    }

    public function test_retry_skipped_materialization_creates_bid_records(): void
    {
        $row = $this->makeAutoApprovedRow();
        $batch = $row->importBatch;

        $service = app(ImportMaterializationService::class);
        $service->materializeRow($row);
        $row->refresh();
        BidRecord::query()->where('source_import_row_id', $row->id)->delete();
        $row->update([
            'bid_record_id' => null,
            'tender_item_id' => null,
            'tender_id' => null,
            'company_id' => null,
            'standardized_drug_id' => null,
        ]);

        $normalized = $row->normalized_data ?? [];
        $normalized['materialization_status'] = 'skipped';
        $normalized['materialization_skip_reason'] = MaterializationEligibilityService::REASON_ALREADY_MATERIALIZED;
        $row->update(['normalized_data' => $normalized]);

        Artisan::call('imports:materialize', [
            '--batch' => $batch->id,
            '--retry-skipped' => true,
        ]);

        $row->refresh();
        $this->assertNotNull($row->bid_record_id);
        $this->assertEquals(1, BidRecord::query()->where('import_batch_id', $batch->id)->count());
    }

    public function test_diagnose_materialization_command_lists_skip_reasons(): void
    {
        $row = $this->makeAutoApprovedRow();
        $normalized = $row->normalized_data ?? [];
        $normalized['price_usd'] = null;
        $row->update(['normalized_data' => $normalized]);
        $batch = $row->importBatch;

        Artisan::call('imports:diagnose-materialization', ['batch' => $batch->id]);

        $output = Artisan::output();
        $this->assertStringContainsString('Skip reasons', $output);
        $this->assertStringContainsString('price', strtolower($output));
    }

    public function test_chunk_eligibility_matches_materialize_row(): void
    {
        $row = $this->makeAutoApprovedRow();
        $batch = $row->importBatch;

        $stats = app(ImportMaterializationService::class)->batchMaterializationStats($batch);
        $this->assertGreaterThanOrEqual(1, $stats['eligible_pending']);

        $summary = app(ImportMaterializationService::class)->materializeBatch($batch);
        $this->assertGreaterThanOrEqual(1, $summary['materialized']);
        $this->assertGreaterThanOrEqual(1, $summary['bid_records_created']);
    }

    protected function makeAutoApprovedRow(array $overrides = []): ImportRow
    {
        $batch = ImportBatch::create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'eligibility.csv',
            'original_filename' => 'eligibility.csv',
            'file_path' => 'imports/eligibility.csv',
            'file_hash' => hash('sha256', uniqid('', true)),
            'row_count' => 1,
            'status' => 'completed',
            'source_type' => 'csv',
        ]);

        $row = ImportRow::create(array_merge([
            'import_batch_id' => $batch->id,
            'row_number' => 1,
            'row_hash' => hash('sha256', uniqid('', true)),
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
            'validation_status' => ImportRowValidationStatus::Valid->value,
            'standardization_status' => StandardizationStatus::Pending->value,
            'row_type' => 'winning_bid',
            'raw_data' => [],
            'normalized_data' => [
                'price_usd' => 425.0,
                'awarded_price' => 420.0,
                'qty' => 1000.0,
                'year' => 2024,
            ],
        ], $overrides));

        app(ImportRowStandardizationService::class)->standardizeRow($row);

        return $row->fresh();
    }
}
