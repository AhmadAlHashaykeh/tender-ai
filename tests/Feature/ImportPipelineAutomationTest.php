<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportRowValidationStatus;
use App\Enums\StandardizationStatus;
use App\Jobs\ProcessImportBatchJob;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use App\Services\Import\ImportJobDispatcher;
use App\Services\Import\ImportPipelineOrchestratorService;
use App\Services\Standardization\ImportRowStandardizationService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\StandardizationReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportPipelineAutomationTest extends TestCase
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

        config([
            'import.pipeline_automation_enabled' => true,
            'import.auto_approve_threshold' => 95,
            'import.show_advanced_details' => false,
            'app.debug' => false,
        ]);
    }

    public function test_upload_and_mapping_confirmation_queues_automated_processing(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $batch = $this->uploadCsv($user, $this->singleCsvRow());

        $this->assertSame(ImportBatchStatus::Queued->value, $batch->status);
        $this->assertSame('async', $batch->fresh()->metadata['processing_mode'] ?? null);

        Queue::assertPushed(ProcessImportBatchJob::class);
    }

    public function test_all_imports_use_async_processing_mode(): void
    {
        $batch = ImportBatch::query()->create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'small.csv',
            'original_filename' => 'small.csv',
            'file_path' => 'imports/small.csv',
            'file_hash' => hash('sha256', 'small'),
            'row_count' => 1,
            'status' => ImportBatchStatus::Queued->value,
            'source_type' => 'csv',
            'metadata' => ['estimated_row_count' => 10],
        ]);

        app(ImportPipelineOrchestratorService::class)->markProcessingMode($batch);

        $this->assertSame('async', $batch->fresh()->metadata['processing_mode']);
        $this->assertSame('async', app(ImportJobDispatcher::class)->processingMode($batch->fresh()));
    }

    public function test_exact_and_alias_matches_auto_approve(): void
    {
        $row = $this->makeRow([
            'raw_country' => 'Saudi Arabia',
            'raw_code' => 'D001',
            'raw_product_name' => 'Paracetamol 500mg',
            'raw_company_name' => 'PharmaCorp International',
        ]);

        $result = app(ImportRowStandardizationService::class)->standardizeRow($row->fresh());

        $this->assertSame(StandardizationStatus::AutoApproved->value, $result['status']);
    }

    public function test_review_required_when_confidence_below_threshold(): void
    {
        config(['import.auto_approve_threshold' => 99]);

        $row = $this->makeRow([
            'raw_country' => 'Atlantis',
            'raw_code' => 'UNKNOWN99',
            'raw_inn' => 'Unknownium',
            'raw_product_name' => 'Unknownium 99mg',
            'raw_company_name' => 'Mystery Vendor Ltd',
        ]);

        $result = app(ImportRowStandardizationService::class)->standardizeRow($row->fresh());

        $this->assertSame(StandardizationStatus::ReviewRequired->value, $result['status']);
    }

    public function test_import_show_displays_review_cta_when_items_need_review(): void
    {
        $user = User::factory()->create();
        $row = $this->makeRow([
            'standardization_status' => StandardizationStatus::ReviewRequired->value,
        ]);

        $this->actingAs($user)
            ->get(route('imports.show', $row->importBatch))
            ->assertOk()
            ->assertSee('Review Matches', false);
    }

    public function test_import_show_displays_ready_state_when_pipeline_ready(): void
    {
        $user = User::factory()->create();
        $batch = ImportBatch::query()->create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'ready.csv',
            'original_filename' => 'ready.csv',
            'file_path' => 'imports/ready.csv',
            'file_hash' => hash('sha256', 'ready'),
            'row_count' => 1,
            'status' => ImportBatchStatus::Completed->value,
            'source_type' => 'csv',
            'metadata' => [
                'pipeline_status' => 'ready',
                'pipeline_ready_at' => now()->toIso8601String(),
                'standardization_status' => 'completed',
                'materialization_status' => 'completed',
                'statistics_status' => 'completed',
                'pricing_statistics_count' => 1,
            ],
        ]);

        $this->actingAs($user)
            ->get(route('imports.show', $batch))
            ->assertOk()
            ->assertSee('Your data is ready', false)
            ->assertSee('Start Predictions', false);
    }

    public function test_approving_review_triggers_materialization_pipeline(): void
    {
        Bus::fake();

        $row = $this->makeRow([
            'raw_country' => 'Saudi Arabia',
            'raw_code' => 'D001',
            'raw_product_name' => 'Paracetamol 500mg',
            'raw_company_name' => 'PharmaCorp International',
        ]);

        $service = app(ImportRowStandardizationService::class);
        $service->standardizeRow($row);
        $row->update(['standardization_status' => StandardizationStatus::ReviewRequired->value]);

        $batch = $row->importBatch;
        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'standardization_status' => 'completed',
                'processing_mode' => 'async',
            ]),
        ]);

        $service->approveRow($row->fresh(), null);

        $this->assertSame(StandardizationStatus::Approved->value, $row->fresh()->standardization_status);
        Bus::assertDispatched(\App\Jobs\MaterializeImportBatchJob::class);
    }

    public function test_import_validation_complete_dispatches_standardization_job(): void
    {
        Bus::fake();

        $batch = ImportBatch::query()->create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'batch.csv',
            'original_filename' => 'batch.csv',
            'file_path' => 'imports/batch.csv',
            'file_hash' => hash('sha256', 'batch'),
            'row_count' => 2,
            'status' => ImportBatchStatus::Completed->value,
            'source_type' => 'csv',
            'metadata' => ['estimated_row_count' => 2],
        ]);

        ImportRow::query()->create([
            'import_batch_id' => $batch->id,
            'row_number' => 1,
            'row_hash' => hash('sha256', 'r1'),
            'validation_status' => ImportRowValidationStatus::Valid->value,
            'standardization_status' => StandardizationStatus::Pending->value,
            'raw_data' => [],
            'normalized_data' => ['price_usd' => 10],
        ]);

        app(ImportPipelineOrchestratorService::class)->onImportValidationComplete($batch);

        Bus::assertDispatched(\App\Jobs\StandardizeImportBatchJob::class);
    }

    public function test_import_job_dispatcher_queues_work(): void
    {
        Queue::fake();

        $batch = ImportBatch::query()->create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'big.csv',
            'original_filename' => 'big.csv',
            'file_path' => 'imports/big.csv',
            'file_hash' => hash('sha256', 'big'),
            'status' => ImportBatchStatus::Queued->value,
            'source_type' => 'csv',
            'metadata' => ['estimated_row_count' => 2000],
        ]);

        app(ImportJobDispatcher::class)->dispatch(new ProcessImportBatchJob($batch), $batch);

        Queue::assertPushed(ProcessImportBatchJob::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeRow(array $overrides = []): ImportRow
    {
        $batch = ImportBatch::query()->create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'test.csv',
            'original_filename' => 'test.csv',
            'file_path' => 'imports/test.csv',
            'file_hash' => hash('sha256', 'test'),
            'row_count' => 1,
            'status' => ImportBatchStatus::Completed->value,
            'source_type' => 'csv',
            'metadata' => [],
        ]);

        return ImportRow::query()->create(array_merge([
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

    private function uploadCsv(User $user, string $csv): ImportBatch
    {
        Queue::fake();

        $file = UploadedFile::fake()->createWithContent('automation_test.csv', $csv);

        $this->actingAs($user)->post(route('uploads.store'), ['file' => $file]);

        $batch = ImportBatch::query()->latest('id')->firstOrFail();

        $mapping = array_filter($batch->metadata['mapped_headers'] ?? [], fn ($index) => $index !== null && $index !== '');

        $this->actingAs($user)->post(route('imports.mapping.confirm', $batch), [
            'mapping' => $mapping,
        ])->assertRedirect(route('imports.show', $batch));

        return $batch->fresh();
    }

    private function singleCsvRow(): string
    {
        $header = 'Code,INN,Product Name,Country,Tender #,Awarded price,Price USD,Winner,Company Name,Version,Year,Qty,Tender Value';

        $row = implode(',', [
            '"D001"',
            '"Paracetamol"',
            '"Paracetamol 500mg"',
            '"Saudi Arabia"',
            '"T-2024-001"',
            '"420"',
            '"425"',
            '"PharmaCorp"',
            '"PharmaCorp International"',
            '"v1"',
            '"2024"',
            '"1000"',
            '"425000"',
        ]);

        return $header."\n".$row."\n";
    }
}
