<?php

namespace Tests\Feature;

use App\Enums\ImportRowValidationStatus;
use App\Enums\MaterializationChunkStatus;
use App\Enums\StandardizationStatus;
use App\Jobs\MaterializeImportBatchJob;
use App\Jobs\MaterializeImportChunkJob;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\MaterializationChunk;
use App\Services\Materialization\MaterializationChunkService;
use App\Services\Standardization\ImportRowStandardizationService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\StandardizationReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MaterializationRecoveryTest extends TestCase
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
            'import.materialization_stuck_chunk_minutes' => 10,
            'queue.default' => 'database',
        ]);
    }

    public function test_dispatch_sets_preparing_not_processing(): void
    {
        Queue::fake();

        $row = $this->makeApprovedRow();
        $batch = $row->importBatch;

        app(MaterializationChunkService::class)->dispatchBatchJob($batch);

        $batch->refresh();
        $this->assertSame('preparing', $batch->metadata['materialization_status']);

        Queue::assertPushed(MaterializeImportBatchJob::class);
    }

    public function test_existing_pending_chunks_are_redispatched_on_resume(): void
    {
        Queue::fake();

        $row = $this->makeApprovedRow();
        $batch = $row->importBatch;

        MaterializationChunk::create([
            'import_batch_id' => $batch->id,
            'chunk_number' => 1,
            'start_row_number' => 1,
            'end_row_number' => 1,
            'status' => MaterializationChunkStatus::Pending->value,
            'total_rows' => 1,
        ]);

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'materialization_status' => 'preparing',
            ]),
        ]);

        app(MaterializationChunkService::class)->resumeOrOrchestrate($batch->fresh());

        Queue::assertPushed(MaterializeImportChunkJob::class, 1);
        $this->assertSame('processing', $batch->fresh()->metadata['materialization_status']);
    }

    public function test_existing_completed_chunks_are_ignored_and_batch_finalizes(): void
    {
        $row = $this->makeApprovedRow();
        $batch = $row->importBatch;

        app(\App\Services\Materialization\ImportMaterializationService::class)->materializeRow($row);
        $row->refresh();

        MaterializationChunk::create([
            'import_batch_id' => $batch->id,
            'chunk_number' => 1,
            'start_row_number' => $row->row_number,
            'end_row_number' => $row->row_number,
            'status' => MaterializationChunkStatus::Completed->value,
            'total_rows' => 1,
            'processed_rows' => 1,
            'materialized_rows' => 1,
            'completed_at' => now(),
        ]);

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'materialization_status' => 'processing',
                'materialization_total_rows' => 1,
            ]),
        ]);

        Queue::fake();
        app(MaterializationChunkService::class)->resumeOrOrchestrate($batch->fresh());

        Queue::assertNotPushed(MaterializeImportChunkJob::class);
        $this->assertSame('completed', $batch->fresh()->metadata['materialization_status']);
    }

    public function test_stuck_processing_chunks_recover_and_redispatch(): void
    {
        Queue::fake();
        config(['import.materialization_stuck_chunk_minutes' => 1]);

        $row = $this->makeApprovedRow();
        $batch = $row->importBatch;

        $chunk = MaterializationChunk::create([
            'import_batch_id' => $batch->id,
            'chunk_number' => 1,
            'start_row_number' => 1,
            'end_row_number' => 1,
            'status' => MaterializationChunkStatus::Processing->value,
            'total_rows' => 1,
            'started_at' => now()->subMinutes(15),
        ]);

        $chunk->forceFill(['updated_at' => now()->subMinutes(15)])->save();

        app(MaterializationChunkService::class)->resumeOrOrchestrate($batch->fresh());

        $chunk->refresh();
        $this->assertSame(MaterializationChunkStatus::Pending->value, $chunk->status);
        $this->assertSame(1, $chunk->retry_count);
        Queue::assertPushed(MaterializeImportChunkJob::class);
    }

    public function test_materialization_batch_job_can_safely_rerun(): void
    {
        Queue::fake();

        $row = $this->makeApprovedRow();
        $batch = $row->importBatch;

        MaterializationChunk::create([
            'import_batch_id' => $batch->id,
            'chunk_number' => 1,
            'start_row_number' => 1,
            'end_row_number' => 1,
            'status' => MaterializationChunkStatus::Pending->value,
            'total_rows' => 1,
        ]);

        (new MaterializeImportBatchJob($batch->id))->handle(app(MaterializationChunkService::class));
        (new MaterializeImportBatchJob($batch->id))->handle(app(MaterializationChunkService::class));

        Queue::assertPushed(MaterializeImportChunkJob::class, 2);
        $this->assertSame('processing', $batch->fresh()->metadata['materialization_status']);
    }

    public function test_queue_failure_does_not_leave_permanent_processing_without_chunks(): void
    {
        Queue::fake();

        $row = $this->makeApprovedRow();
        $batch = $row->importBatch;

        app(MaterializationChunkService::class)->dispatchBatchJob($batch);

        $this->assertSame('preparing', $batch->fresh()->metadata['materialization_status']);
        $this->assertFalse($batch->fresh()->usesChunkedMaterialization());
    }

    public function test_resume_orchestrate_transitions_to_processing_after_dispatch(): void
    {
        Queue::fake();

        $row = $this->makeApprovedRow();
        $batch = $row->importBatch;

        app(MaterializationChunkService::class)->resumeOrOrchestrate($batch);

        $batch->refresh();
        $this->assertTrue($batch->usesChunkedMaterialization());
        $this->assertSame('processing', $batch->metadata['materialization_status']);
        Queue::assertPushed(MaterializeImportChunkJob::class);
    }

    public function test_cron_processor_command_is_registered(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString('queue:process-pending', $output);
    }

    public function test_process_pending_queue_command_processes_materialization_jobs(): void
    {
        $row = $this->makeApprovedRow();
        $batch = $row->importBatch;

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'materialization_status' => 'preparing',
                'standardization_status' => 'completed',
            ]),
        ]);

        dispatch(new MaterializeImportBatchJob($batch->id));

        Artisan::call('queue:process-pending', ['--max-jobs' => 10, '--timeout' => 120]);

        $batch->refresh();
        $this->assertTrue($batch->usesChunkedMaterialization());
        $this->assertContains(
            $batch->metadata['materialization_status'],
            ['processing', 'completed'],
            'Cron processor should advance materialization beyond preparing.',
        );
    }

    protected function makeApprovedRow(): ImportRow
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
            'metadata' => ['standardization_status' => 'completed'],
        ]);

        $row = ImportRow::create([
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
            'raw_awarded_price' => '420',
            'raw_qty' => '1000',
            'raw_tender_value' => '425000',
            'validation_status' => ImportRowValidationStatus::Valid->value,
            'standardization_status' => StandardizationStatus::Pending->value,
            'row_type' => 'winning_bid',
            'raw_data' => [],
            'normalized_data' => [
                'price_usd' => 425.0,
                'awarded_price' => 420.0,
                'qty' => 1000.0,
                'tender_value' => 425000.0,
                'year' => 2024,
            ],
        ]);

        app(ImportRowStandardizationService::class)->standardizeRow($row);

        return $row->fresh();
    }
}
