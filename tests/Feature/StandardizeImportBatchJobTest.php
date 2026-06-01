<?php

namespace Tests\Feature;

use App\Enums\ImportRowValidationStatus;
use App\Enums\StandardizationStatus;
use App\Jobs\StandardizeImportBatchJob;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use App\Services\Standardization\ImportRowStandardizationService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\StandardizationReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class StandardizeImportBatchJobTest extends TestCase
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

    public function test_run_batch_route_dispatches_job_instead_of_processing_synchronously(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $batch = $this->createBatchWithPendingRows(5);

        $response = $this->actingAs($user)->post(route('standardization.run-batch', $batch));

        Queue::assertPushed(StandardizeImportBatchJob::class, function (StandardizeImportBatchJob $job) use ($batch) {
            return $job->importBatchId === $batch->id;
        });

        $response->assertRedirect(route('imports.show', $batch));
        $response->assertSessionHas('success');

        $batch->refresh();
        $this->assertSame('processing', $batch->metadata['standardization_status']);
        $this->assertSame(5, $batch->metadata['standardization_total_rows']);
    }

    public function test_job_processes_only_pending_rows_and_updates_progress_metadata(): void
    {
        $batch = $this->createBatchWithPendingRows(3);

        ImportRow::query()->create([
            'import_batch_id' => $batch->id,
            'row_number' => 99,
            'row_hash' => hash('sha256', 'already-done'),
            'raw_data' => [],
            'validation_status' => ImportRowValidationStatus::Valid->value,
            'standardization_status' => StandardizationStatus::AutoApproved->value,
            'normalized_data' => ['price_usd' => 12.5],
            'raw_country' => 'Saudi Arabia',
            'raw_year' => '2024',
            'raw_product_name' => 'Paracetamol 500mg',
            'raw_company_name' => 'PharmaCorp International',
            'raw_tender_number' => 'T-2024-001',
        ]);

        $summary = app(ImportRowStandardizationService::class)->standardizeBatchWithProgress($batch->fresh(), onlyPending: true);

        $batch->refresh();

        $this->assertSame(3, $summary['processed']);
        $this->assertSame('completed', $batch->metadata['standardization_status']);
        $this->assertSame(3, $batch->metadata['standardization_processed_rows']);
        $this->assertSame(3, $batch->metadata['standardization_total_rows']);
        $this->assertArrayHasKey('standardization_summary', $batch->metadata);
        $this->assertSame(0, ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->where('standardization_status', StandardizationStatus::Pending->value)
            ->count());
        $this->assertSame(
            StandardizationStatus::AutoApproved->value,
            ImportRow::query()->where('import_batch_id', $batch->id)->where('row_number', 99)->value('standardization_status')
        );
    }

    public function test_failed_row_does_not_kill_entire_batch(): void
    {
        $batch = $this->createBatchWithPendingRows(2);
        $firstRow = ImportRow::query()->where('import_batch_id', $batch->id)->orderBy('row_number')->firstOrFail();

        $service = new class(
            app(\App\Services\Standardization\CountryStandardizationService::class),
            app(\App\Services\Standardization\CompanyStandardizationService::class),
            app(\App\Services\Standardization\DrugStandardizationService::class),
            app(\App\Services\Standardization\TenderStandardizationService::class),
            app(\App\Services\Settings\SettingsService::class),
            app(\App\Services\Standardization\EntityMatchIndexService::class),
            app(\App\Services\Import\ImportBatchService::class),
            $firstRow->id,
        ) extends ImportRowStandardizationService
        {
            public function __construct(
                $countryService,
                $companyService,
                $drugService,
                $tenderService,
                $settings,
                $matchIndex,
                $importBatchService,
                protected int $failRowId,
            ) {
                parent::__construct($countryService, $companyService, $drugService, $tenderService, $settings, $matchIndex, $importBatchService);
            }

            public function standardizeRow(ImportRow $row, bool $persist = true): array
            {
                if ($row->id === $this->failRowId) {
                    throw new RuntimeException('Simulated row failure');
                }

                return parent::standardizeRow($row, $persist);
            }
        };

        $summary = $service->standardizeBatchWithProgress($batch->fresh(), onlyPending: true);

        $batch->refresh();

        $this->assertSame(2, $summary['processed']);
        $this->assertSame(1, $summary['failed']);
        $this->assertSame('completed', $batch->metadata['standardization_status']);
        $this->assertSame(0, ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->where('standardization_status', StandardizationStatus::Pending->value)
            ->count());
    }

    public function test_import_show_displays_running_state_while_processing(): void
    {
        $user = User::factory()->create();
        $batch = $this->createBatchWithPendingRows(4);

        $batch->update([
            'status' => 'completed',
            'metadata' => array_merge($batch->metadata ?? [], [
                'standardization_status' => 'processing',
                'standardization_processed_rows' => 2,
                'standardization_total_rows' => 4,
            ]),
        ]);

        $response = $this->actingAs($user)->get(route('imports.show', $batch));

        $response->assertOk();
        $response->assertSee('Standardizing Import');
        $response->assertSee('Standardization Running...', false);
        $response->assertSee('Standardization Running...', false);
    }

    protected function createBatchWithPendingRows(int $count): ImportBatch
    {
        $user = User::factory()->create();

        $batch = ImportBatch::query()->create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'test.csv',
            'original_filename' => 'test.csv',
            'uploaded_by' => $user->id,
            'row_count' => $count,
            'status' => 'completed',
            'metadata' => [],
        ]);

        for ($i = 1; $i <= $count; $i++) {
            ImportRow::query()->create([
                'import_batch_id' => $batch->id,
                'row_number' => $i,
                'row_hash' => hash('sha256', 'row-'.$i),
                'raw_data' => [],
                'validation_status' => ImportRowValidationStatus::Valid->value,
                'standardization_status' => StandardizationStatus::Pending->value,
                'normalized_data' => ['price_usd' => 10.0 + $i],
                'raw_country' => 'Saudi Arabia',
                'raw_year' => '2024',
                'raw_product_name' => 'Paracetamol 500mg',
                'raw_company_name' => 'PharmaCorp International',
                'raw_tender_number' => 'T-2024-00'.$i,
            ]);
        }

        return $batch->fresh();
    }
}
