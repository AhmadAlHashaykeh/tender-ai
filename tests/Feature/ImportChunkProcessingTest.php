<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportChunkStatus;
use App\Enums\ImportRowValidationStatus;
use App\Jobs\ProcessImportChunkJob;
use App\Models\ImportBatch;
use App\Models\ImportChunk;
use App\Models\ImportRow;
use App\Models\User;
use App\Services\Import\ImportChunkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportChunkProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_file_creates_multiple_chunks(): void
    {
        config(['import.chunk_size' => 2]);

        $user = User::factory()->create();
        $csv = $this->buildCsvWithRows(5);

        $batch = $this->uploadAndConfirm($user, $csv);

        $this->assertTrue($batch->usesChunkedImport());
        $this->assertEquals(3, ImportChunk::query()->where('import_batch_id', $batch->id)->count());
        $this->assertEquals(5, $batch->row_count);
    }

    public function test_mapping_confirmation_dispatches_chunk_jobs(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $path = base_path('tests/fixtures/sample_tender_import.csv');
        $file = new UploadedFile($path, 'sample.csv', 'text/csv', null, true);

        $this->actingAs($user)->post(route('uploads.store'), ['file' => $file]);
        $batch = ImportBatch::query()->latest()->firstOrFail();

        $mapping = array_filter($batch->metadata['mapped_headers'] ?? [], fn ($i) => $i !== null);

        $this->actingAs($user)->post(route('imports.mapping.confirm', $batch), [
            'mapping' => $mapping,
        ])->assertRedirect(route('imports.show', $batch));

        Queue::assertPushed(\App\Jobs\ProcessImportBatchJob::class);
    }

    public function test_progress_endpoint_returns_chunk_metrics(): void
    {
        config(['import.chunk_size' => 2]);

        $user = User::factory()->create();
        $batch = $this->uploadAndConfirm($user, $this->buildCsvWithRows(4));

        $response = $this->actingAs($user)->getJson(route('imports.progress', $batch));

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'progress',
                'processed_rows',
                'total_rows',
                'completed_chunks',
                'total_chunks',
                'valid_rows',
                'warning_rows',
                'invalid_rows',
                'duplicate_rows',
                'failed_chunks',
                'is_complete',
                'uses_chunks',
            ]);

        $this->assertTrue($response->json('uses_chunks'));
        $this->assertEquals(2, $response->json('total_chunks'));
    }

    public function test_completed_chunks_update_batch_counters(): void
    {
        config(['import.chunk_size' => 10]);

        $user = User::factory()->create();
        $batch = $this->uploadAndConfirm($user, $this->buildCsvWithRows(3));

        $batch = $batch->fresh();

        $this->assertContains($batch->status, [
            ImportBatchStatus::Completed->value,
            ImportBatchStatus::CompletedWithErrors->value,
        ]);
        $this->assertEquals(3, $batch->processed_count);
        $this->assertGreaterThan(0, $batch->success_count + $batch->error_count);
    }

    public function test_failed_chunk_does_not_fail_whole_batch(): void
    {
        config(['import.chunk_size' => 2]);

        $user = User::factory()->create();
        $batch = $this->uploadAndConfirm($user, $this->buildCsvWithRows(4));

        $chunk = ImportChunk::query()
            ->where('import_batch_id', $batch->id)
            ->where('chunk_number', 2)
            ->firstOrFail();

        $chunk->update([
            'status' => ImportChunkStatus::Failed->value,
            'error_message' => 'Simulated failure',
            'completed_at' => now(),
        ]);

        ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->whereIn('row_number', [4, 5])
            ->delete();

        app(ImportChunkService::class)->checkBatchFinalization($batch->fresh());

        $batch = $batch->fresh();

        $this->assertEquals(ImportBatchStatus::CompletedWithErrors->value, $batch->status);
        $this->assertGreaterThan(0, ImportRow::query()->where('import_batch_id', $batch->id)->count());
    }

    public function test_retry_failed_chunks_only_retries_failed_chunks(): void
    {
        config(['import.chunk_size' => 2]);

        $user = User::factory()->create();
        $batch = $this->uploadAndConfirm($user, $this->buildCsvWithRows(4));

        Queue::fake();

        $failed = ImportChunk::query()
            ->where('import_batch_id', $batch->id)
            ->orderByDesc('chunk_number')
            ->firstOrFail();

        $failed->update([
            'status' => ImportChunkStatus::Failed->value,
            'error_message' => 'Test failure',
            'completed_at' => now(),
        ]);

        $completed = ImportChunk::query()
            ->where('import_batch_id', $batch->id)
            ->where('status', ImportChunkStatus::Completed->value)
            ->count();

        $this->assertEquals(1, $completed);

        $this->actingAs($user)
            ->post(route('imports.chunks.retry-failed', $batch))
            ->assertRedirect(route('imports.show', $batch));

        Queue::assertPushed(ProcessImportChunkJob::class, 1);

        $failed->refresh();
        $this->assertEquals(ImportChunkStatus::Pending->value, $failed->status);

        $stillCompleted = ImportChunk::query()
            ->where('import_batch_id', $batch->id)
            ->where('status', ImportChunkStatus::Completed->value)
            ->count();

        $this->assertEquals(1, $stillCompleted);
    }

    public function test_legacy_batch_without_chunks_renders_progress(): void
    {
        $user = User::factory()->create();

        $batch = ImportBatch::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'filename' => 'legacy.csv',
            'original_filename' => 'legacy.csv',
            'status' => ImportBatchStatus::Completed->value,
            'row_count' => 10,
            'processed_count' => 10,
            'success_count' => 8,
            'error_count' => 2,
            'metadata' => ['valid_rows' => 8, 'invalid_rows' => 2],
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson(route('imports.progress', $batch));

        $response->assertOk();
        $this->assertFalse($response->json('uses_chunks'));
        $this->assertTrue($response->json('is_complete'));
        $this->assertEquals(100, $response->json('progress'));
    }

    public function test_duplicate_detection_across_chunks(): void
    {
        config(['import.chunk_size' => 1]);

        $user = User::factory()->create();

        $row1 = $this->csvDataRow('D001', 'Paracetamol', 'Paracetamol 500mg', 'Saudi Arabia', 'T-1', '100', '100', 'Co', 'Co', 'v1', '2024', '10', '1000');
        $row2 = $this->csvDataRow('D001', 'Paracetamol', 'Paracetamol 500mg', 'Saudi Arabia', 'T-1', '100', '100', 'Co', 'Co', 'v1', '2024', '10', '1000');
        $csv = $this->csvHeader()."\n".$row1."\n".$row2."\n";

        $batch = $this->uploadAndConfirm($user, $csv);

        $duplicates = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->where('validation_status', ImportRowValidationStatus::Duplicate->value)
            ->count();

        $this->assertGreaterThanOrEqual(1, $duplicates);
        $this->assertEquals(2, ImportChunk::query()->where('import_batch_id', $batch->id)->count());
    }

    private function uploadAndConfirm(User $user, string $csv): ImportBatch
    {
        $file = UploadedFile::fake()->createWithContent('chunk_test.csv', $csv);

        $this->actingAs($user)->post(route('uploads.store'), ['file' => $file]);

        $batch = ImportBatch::query()->latest('id')->firstOrFail();
        $mapping = array_filter($batch->metadata['mapped_headers'] ?? [], fn ($i) => $i !== null);

        $this->actingAs($user)->post(route('imports.mapping.confirm', $batch), [
            'mapping' => $mapping,
        ]);

        return $batch->fresh();
    }

    private function buildCsvWithRows(int $count): string
    {
        $lines = [$this->csvHeader()];

        for ($i = 1; $i <= $count; $i++) {
            $lines[] = $this->csvDataRow(
                'D'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'Drug '.$i,
                'Product '.$i,
                'Saudi Arabia',
                'T-'.$i,
                (string) (100 + $i),
                (string) (100 + $i),
                'Winner '.$i,
                'Company '.$i,
                'v1',
                '2024',
                '10',
                (string) (1000 + $i),
            );
        }

        return implode("\n", $lines)."\n";
    }

    private function csvHeader(): string
    {
        return 'Code,INN,Product Name,Country,Tender #,Awarded price,Price USD,Winner,Company Name,Version,Year,Qty,Tender Value';
    }

    private function csvDataRow(
        string $code,
        string $inn,
        string $productName,
        string $country,
        string $tenderNumber,
        string $awardedPrice,
        string $priceUsd,
        string $winner,
        string $companyName,
        string $version,
        string $year,
        string $qty,
        string $tenderValue,
    ): string {
        $escape = static fn (string $value): string => '"'.str_replace('"', '""', $value).'"';

        return implode(',', [
            $escape($code),
            $escape($inn),
            $escape($productName),
            $escape($country),
            $escape($tenderNumber),
            $escape($awardedPrice),
            $escape($priceUsd),
            $escape($winner),
            $escape($companyName),
            $escape($version),
            $escape($year),
            $escape($qty),
            $escape($tenderValue),
        ]);
    }
}
