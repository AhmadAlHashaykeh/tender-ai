<?php

namespace Tests\Feature;

use App\Enums\ImportRowValidationStatus;
use App\Enums\StandardizationStatus;
use App\Jobs\StandardizeImportChunkJob;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\StandardizationChunk;
use App\Models\User;
use App\Services\Materialization\ImportMaterializationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportShowPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_show_does_not_load_all_import_rows(): void
    {
        $user = User::factory()->create();
        $batch = $this->createLargeBatch(250);

        DB::enableQueryLog();

        $response = $this->actingAs($user)->get(route('imports.show', $batch));

        $queries = collect(DB::getQueryLog());
        $importRowSelectAll = $queries->filter(function (array $query) {
            $sql = strtolower($query['query']);

            return str_contains($sql, 'from "import_rows"')
                && ! str_contains($sql, 'count(')
                && ! str_contains($sql, 'limit')
                && ! str_contains($sql, 'group by')
                && ! str_contains($sql, 'exists');
        });

        $response->assertOk();
        $response->assertDontSee('Row Preview (first 50)', false);
        $response->assertSee('Full Preview', false);
        $this->assertCount(0, $importRowSelectAll, 'Show page must not SELECT * FROM import_rows without LIMIT');
    }

    public function test_materialization_stats_use_aggregate_queries_not_per_row_exists(): void
    {
        $batch = $this->createLargeBatch(120);

        ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->limit(10)
            ->update([
                'standardization_status' => StandardizationStatus::AutoApproved->value,
                'validation_status' => ImportRowValidationStatus::Valid->value,
                'normalized_data' => [
                    'price_usd' => 99.5,
                    'country_id' => 1,
                    'standardization' => ['tender' => ['tender_number' => 'T-1']],
                ],
            ]);

        DB::enableQueryLog();

        $stats = app(ImportMaterializationService::class)->batchMaterializationStats($batch);

        $queries = collect(DB::getQueryLog());
        $existsPerRow = $queries->filter(fn (array $q) => preg_match('/exists.*bid_records.*source_import_row_id/i', $q['query']) && ! preg_match('/count|join/i', $q['query']));

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('materialized', $stats);
        $this->assertLessThanOrEqual(12, $queries->count(), 'Materialization stats should use a small number of aggregate queries');
        $this->assertCount(0, $existsPerRow, 'Must not run per-row EXISTS on bid_records');
    }

    public function test_standardization_creates_chunk_jobs(): void
    {
        Queue::fake();

        $batch = $this->createLargeBatch(250);

        app(\App\Services\Standardization\StandardizationChunkService::class)->orchestrate($batch->fresh());

        $this->assertGreaterThan(1, StandardizationChunk::query()->where('import_batch_id', $batch->id)->count());
        $this->assertEquals(250, ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->where('standardization_status', StandardizationStatus::Pending->value)
            ->count());
        Queue::assertPushed(StandardizeImportChunkJob::class);
    }

    public function test_progress_endpoint_includes_standardization_metrics(): void
    {
        $user = User::factory()->create();
        $batch = $this->createLargeBatch(10);

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'standardization_status' => 'processing',
                'standardization_processed_rows' => 4,
                'standardization_total_rows' => 10,
                'standardization_total_chunks' => 2,
                'standardization_completed_chunks' => 1,
                'standardization_summary' => [
                    'auto_approved' => 3,
                    'review_required' => 1,
                    'rejected' => 0,
                    'failed' => 0,
                ],
            ]),
        ]);

        $response = $this->actingAs($user)->getJson(route('imports.progress', $batch));

        $response->assertOk()
            ->assertJsonPath('standardization.status', 'processing')
            ->assertJsonPath('standardization.processed_rows', 4)
            ->assertJsonPath('standardization.total_rows', 10)
            ->assertJsonPath('standardization.completed_chunks', 1)
            ->assertJsonPath('standardization.total_chunks', 2);
    }

    public function test_retry_failed_standardization_chunks_only_retries_failed(): void
    {
        $batch = $this->createLargeBatch(200);
        app(\App\Services\Standardization\StandardizationChunkService::class)->orchestrate($batch->fresh());

        $failed = StandardizationChunk::query()
            ->where('import_batch_id', $batch->id)
            ->orderByDesc('chunk_number')
            ->firstOrFail();

        $failed->update(['status' => 'failed', 'error_message' => 'test']);

        $completedAfterFail = StandardizationChunk::query()
            ->where('import_batch_id', $batch->id)
            ->where('status', 'completed')
            ->count();

        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('standardization.retry-failed', $batch))
            ->assertRedirect(route('imports.show', $batch));

        Queue::assertPushed(StandardizeImportChunkJob::class, 1);
        $this->assertEquals('pending', $failed->fresh()->status);
        $this->assertEquals($completedAfterFail, StandardizationChunk::query()
            ->where('import_batch_id', $batch->id)
            ->where('status', 'completed')
            ->count());
    }

    public function test_legacy_batch_without_standardization_chunks_renders_show(): void
    {
        $user = User::factory()->create();

        $batch = ImportBatch::query()->create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'legacy.csv',
            'original_filename' => 'legacy.csv',
            'status' => 'completed',
            'row_count' => 5,
            'metadata' => [
                'valid_rows' => 5,
                'import_quality_score' => 80,
                'import_quality_rating' => 'Good',
            ],
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('imports.show', $batch))
            ->assertOk()
            ->assertSee('legacy.csv');
    }

    protected function createLargeBatch(int $rowCount): ImportBatch
    {
        $user = User::factory()->create();

        $batch = ImportBatch::query()->create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'large.csv',
            'original_filename' => 'large.csv',
            'uploaded_by' => $user->id,
            'row_count' => $rowCount,
            'status' => 'completed',
            'metadata' => [
                'valid_rows' => $rowCount,
                'invalid_rows' => 0,
                'warning_rows' => 0,
                'duplicate_rows' => 0,
                'import_quality_score' => 90,
                'import_quality_rating' => 'Excellent',
            ],
            'completed_at' => now(),
        ]);

        $rows = [];

        for ($i = 1; $i <= $rowCount; $i++) {
            $rows[] = [
                'import_batch_id' => $batch->id,
                'row_number' => $i,
                'row_hash' => hash('sha256', 'row-'.$i),
                'raw_data' => json_encode([]),
                'validation_status' => ImportRowValidationStatus::Valid->value,
                'standardization_status' => StandardizationStatus::Pending->value,
                'normalized_data' => json_encode(['price_usd' => 10 + $i]),
                'raw_country' => 'Saudi Arabia',
                'raw_year' => '2024',
                'raw_product_name' => 'Product '.$i,
                'raw_company_name' => 'Company '.$i,
                'raw_tender_number' => 'T-'.$i,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            ImportRow::query()->insert($chunk);
        }

        return $batch->fresh();
    }
}
