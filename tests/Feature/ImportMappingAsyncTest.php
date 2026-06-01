<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Jobs\ProcessImportBatchJob;
use App\Models\ImportBatch;
use App\Models\User;
use App\Services\Import\ImportBatchService;
use App\Services\Import\ImportJobDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportMappingAsyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapping_confirmation_only_queues_jobs(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $batch = $this->batchAwaitingMapping();

        $mapping = [
            'country' => 0,
            'year' => 1,
            'price_usd' => 2,
            'product_name' => 3,
        ];

        $this->actingAs($user)
            ->post(route('imports.mapping.confirm', $batch), ['mapping' => $mapping])
            ->assertRedirect(route('imports.show', $batch));

        $batch->refresh();
        $this->assertSame(ImportBatchStatus::Queued->value, $batch->status);
        $this->assertSame('async', $batch->metadata['processing_mode'] ?? null);
        $this->assertArrayHasKey('confirmed_mapping', $batch->metadata);

        Queue::assertPushed(ProcessImportBatchJob::class, fn (ProcessImportBatchJob $job) => $job->importBatch->id === $batch->id);
    }

    public function test_confirm_mapping_service_does_not_import_rows(): void
    {
        Queue::fake();

        $batch = $this->batchAwaitingMapping();
        $mapping = ['country' => 0, 'year' => 1, 'price_usd' => 2, 'product_name' => 3];

        $service = app(ImportBatchService::class);
        $service->confirmMapping($batch, $mapping);
        $service->dispatchImportProcessing($batch->fresh());

        $this->assertDatabaseCount('import_rows', 0);
        Queue::assertPushed(ProcessImportBatchJob::class);
    }

    public function test_mapping_confirmation_response_is_fast(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $batch = $this->batchAwaitingMapping();

        $started = microtime(true);

        $this->actingAs($user)
            ->post(route('imports.mapping.confirm', $batch), [
                'mapping' => [
                    'country' => 0,
                    'year' => 1,
                    'price_usd' => 2,
                    'product_name' => 3,
                ],
            ])
            ->assertRedirect();

        $elapsed = microtime(true) - $started;

        $this->assertLessThan(2.0, $elapsed, 'Mapping confirmation should return in under 2 seconds.');
    }

    public function test_import_job_dispatcher_always_queues(): void
    {
        Queue::fake();

        $batch = ImportBatch::query()->create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'small.csv',
            'original_filename' => 'small.csv',
            'file_path' => 'imports/small.csv',
            'file_hash' => hash('sha256', 'small'),
            'row_count' => 0,
            'status' => ImportBatchStatus::Queued->value,
            'source_type' => 'csv',
            'metadata' => ['estimated_row_count' => 10],
        ]);

        app(ImportJobDispatcher::class)->dispatch(new ProcessImportBatchJob($batch), $batch);

        Queue::assertPushed(ProcessImportBatchJob::class);
        $this->assertSame('async', app(ImportJobDispatcher::class)->processingMode($batch));
    }

    public function test_web_stack_does_not_run_queue_worker_on_request(): void
    {
        $this->assertFalse(
            class_exists(\App\Http\Middleware\ProcessPendingQueueOnRequest::class),
            'Request-time queue processing middleware must be removed.'
        );
    }

    public function test_scheduler_registers_queue_processor(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString('queue:process-pending', $output);
    }

    protected function batchAwaitingMapping(): ImportBatch
    {
        return ImportBatch::query()->create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'test.csv',
            'original_filename' => 'test.csv',
            'file_path' => 'imports/test.csv',
            'file_hash' => hash('sha256', 'mapping-test'),
            'row_count' => 0,
            'status' => ImportBatchStatus::AwaitingMapping->value,
            'source_type' => 'csv',
            'metadata' => [
                'detected_headers' => ['Country', 'Year', 'Price USD', 'Product Name'],
                'mapped_headers' => [
                    'country' => 0,
                    'year' => 1,
                    'price_usd' => 2,
                    'product_name' => 3,
                ],
                'estimated_row_count' => 100,
            ],
        ]);
    }
}
