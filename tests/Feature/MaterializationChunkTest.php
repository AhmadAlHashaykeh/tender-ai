<?php

namespace Tests\Feature;

use App\Enums\ImportRowValidationStatus;
use App\Enums\MaterializationChunkStatus;
use App\Enums\StandardizationStatus;
use App\Jobs\MaterializeImportBatchJob;
use App\Jobs\MaterializeImportChunkJob;
use App\Models\BidRecord;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\MaterializationChunk;
use App\Models\User;
use App\Services\Materialization\ImportMaterializationService;
use App\Services\Materialization\MaterializationChunkService;
use App\Services\Standardization\ImportRowStandardizationService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\StandardizationReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MaterializationChunkTest extends TestCase
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

    public function test_controller_dispatches_materialization_job_without_sync_work(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $row = $this->makeApprovedRow();
        $batch = $row->importBatch;

        $response = $this->actingAs($user)->post(route('imports.materialize', $batch));

        $response->assertRedirect(route('imports.show', $batch));
        $response->assertSessionHas('success', 'Materialization has started in the background.');

        Queue::assertPushed(MaterializeImportBatchJob::class, fn ($job) => $job->importBatchId === $batch->id);

        $batch->refresh();
        $this->assertEquals('preparing', $batch->metadata['materialization_status']);
    }

    public function test_orchestrator_creates_materialization_chunks(): void
    {
        Queue::fake();

        $row = $this->makeApprovedRow();
        $batch = $row->importBatch;

        app(MaterializationChunkService::class)->orchestrate($batch);

        $this->assertTrue($batch->fresh()->usesChunkedMaterialization());
        $this->assertDatabaseHas('materialization_chunks', [
            'import_batch_id' => $batch->id,
            'status' => MaterializationChunkStatus::Pending->value,
        ]);

        Queue::assertPushed(MaterializeImportChunkJob::class);
    }

    public function test_chunk_processes_only_eligible_rows(): void
    {
        $approved = $this->makeApprovedRow();
        $batch = $approved->importBatch;

        $reviewRow = ImportRow::create(array_merge($this->rowDefaults($batch), [
            'row_number' => 2,
            'row_hash' => hash('sha256', 'review'),
            'standardization_status' => StandardizationStatus::ReviewRequired->value,
        ]));

        $chunk = MaterializationChunk::create([
            'import_batch_id' => $batch->id,
            'chunk_number' => 1,
            'start_row_number' => 1,
            'end_row_number' => 2,
            'status' => MaterializationChunkStatus::Pending->value,
            'total_rows' => 2,
        ]);

        app(MaterializationChunkService::class)->processChunk($chunk);

        $chunk->refresh();
        $this->assertEquals(MaterializationChunkStatus::Completed->value, $chunk->status);
        $this->assertEquals(1, $chunk->processed_rows);
        $this->assertEquals(1, $chunk->materialized_rows);
        $this->assertEquals(0, $chunk->skipped_rows);
        $this->assertNull($reviewRow->fresh()->bid_record_id);
    }

    public function test_already_materialized_row_is_skipped(): void
    {
        $row = $this->makeApprovedRow();
        app(ImportMaterializationService::class)->materializeRow($row);
        $row->refresh();

        $chunk = MaterializationChunk::create([
            'import_batch_id' => $row->import_batch_id,
            'chunk_number' => 1,
            'start_row_number' => $row->row_number,
            'end_row_number' => $row->row_number,
            'status' => MaterializationChunkStatus::Pending->value,
            'total_rows' => 1,
        ]);

        app(MaterializationChunkService::class)->processChunk($chunk);

        $this->assertEquals(1, $chunk->fresh()->skipped_rows);
        $this->assertEquals(0, $chunk->fresh()->materialized_rows);
        $this->assertEquals(1, BidRecord::where('source_import_row_id', $row->id)->count());
    }

    public function test_failed_row_does_not_fail_entire_chunk(): void
    {
        $row = $this->makeApprovedRow([
            'normalized_data' => ['price_usd' => 100.0],
        ]);
        $batch = $row->importBatch;

        $good = ImportRow::create(array_merge($this->rowDefaults($batch), [
            'row_number' => 2,
            'row_hash' => hash('sha256', 'good'),
            'raw_tender_number' => 'T-2024-002',
        ]));
        app(ImportRowStandardizationService::class)->standardizeRow($good);

        $chunk = MaterializationChunk::create([
            'import_batch_id' => $batch->id,
            'chunk_number' => 1,
            'start_row_number' => 1,
            'end_row_number' => 2,
            'status' => MaterializationChunkStatus::Pending->value,
            'total_rows' => 2,
        ]);

        app(MaterializationChunkService::class)->processChunk($chunk);

        $chunk->refresh();
        $this->assertEquals(MaterializationChunkStatus::Completed->value, $chunk->status);
        $this->assertGreaterThanOrEqual(1, $chunk->failed_rows + $chunk->materialized_rows);
        $this->assertNotNull($good->fresh()->bid_record_id);
    }

    public function test_progress_metadata_updates_during_chunk_processing(): void
    {
        $row = $this->makeApprovedRow();
        $batch = $row->importBatch;

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'materialization_status' => 'processing',
                'materialization_total_rows' => 1,
            ]),
        ]);

        $chunk = MaterializationChunk::create([
            'import_batch_id' => $batch->id,
            'chunk_number' => 1,
            'start_row_number' => $row->row_number,
            'end_row_number' => $row->row_number,
            'status' => MaterializationChunkStatus::Pending->value,
            'total_rows' => 1,
        ]);

        app(MaterializationChunkService::class)->processChunk($chunk);
        app(MaterializationChunkService::class)->checkBatchFinalization($batch->fresh());

        $batch->refresh();
        $this->assertEquals('completed', $batch->metadata['materialization_status']);
        $this->assertEquals(1, $batch->metadata['materialization_materialized_rows']);
        $this->assertNotNull($batch->metadata['materialization_completed_at']);
    }

    public function test_retry_failed_chunks_only_retries_failed(): void
    {
        Queue::fake();

        $row = $this->makeApprovedRow();
        $batch = $row->importBatch;

        $failed = MaterializationChunk::create([
            'import_batch_id' => $batch->id,
            'chunk_number' => 1,
            'start_row_number' => 1,
            'end_row_number' => 1,
            'status' => MaterializationChunkStatus::Failed->value,
            'total_rows' => 1,
            'error_message' => 'timeout',
        ]);

        MaterializationChunk::create([
            'import_batch_id' => $batch->id,
            'chunk_number' => 2,
            'start_row_number' => 2,
            'end_row_number' => 2,
            'status' => MaterializationChunkStatus::Completed->value,
            'total_rows' => 1,
            'materialized_rows' => 1,
        ]);

        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(route('imports.materialization.retry-failed', $batch));

        $response->assertRedirect(route('imports.show', $batch));
        $response->assertSessionHas('success');

        $this->assertEquals(MaterializationChunkStatus::Pending->value, $failed->fresh()->status);
        Queue::assertPushed(MaterializeImportChunkJob::class, 1);
    }

    public function test_import_show_displays_materialization_progress_when_processing(): void
    {
        $row = $this->makeApprovedRow();
        $batch = $row->importBatch;

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'materialization_status' => 'processing',
                'materialization_total_rows' => 100,
                'materialization_processed_rows' => 50,
                'materialization_total_chunks' => 4,
                'materialization_completed_chunks' => 2,
            ]),
        ]);

        MaterializationChunk::create([
            'import_batch_id' => $batch->id,
            'chunk_number' => 1,
            'start_row_number' => 1,
            'end_row_number' => 50,
            'status' => MaterializationChunkStatus::Processing->value,
            'total_rows' => 50,
        ]);

        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('imports.show', $batch));

        $response->assertOk();
        $response->assertSee('Materialization in progress', false);
        $response->assertSee('materialization-progress-panel', false);
    }

    protected function makeApprovedRow(array $overrides = []): ImportRow
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

        $row = ImportRow::create(array_merge($this->rowDefaults($batch), [
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
        ], $overrides));

        app(ImportRowStandardizationService::class)->standardizeRow($row);

        return $row->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rowDefaults(ImportBatch $batch): array
    {
        return [
            'import_batch_id' => $batch->id,
            'row_number' => 1,
            'row_hash' => hash('sha256', uniqid('', true)),
            'raw_code' => 'D002',
            'raw_inn' => 'Ibuprofen',
            'raw_product_name' => 'Ibuprofen 200mg',
            'raw_country' => 'Saudi Arabia',
            'raw_company_name' => 'PharmaCorp International',
            'raw_winner' => 'PharmaCorp',
            'raw_tender_number' => 'T-2024-001',
            'raw_year' => '2024',
            'raw_version' => 'v1',
            'raw_price_usd' => '200',
            'raw_awarded_price' => '195',
            'raw_qty' => '500',
            'raw_tender_value' => '100000',
            'validation_status' => ImportRowValidationStatus::Valid->value,
            'standardization_status' => StandardizationStatus::Pending->value,
            'row_type' => 'winning_bid',
            'raw_data' => [],
            'normalized_data' => [
                'price_usd' => 200.0,
                'awarded_price' => 195.0,
                'qty' => 500.0,
                'tender_value' => 100000.0,
                'year' => 2024,
            ],
        ];
    }
}
