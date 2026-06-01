<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Services\Queue\QueueHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'database']);
        Cache::forget(QueueHealthService::LAST_PROCESSED_CACHE_KEY);
    }

    public function test_admin_warning_when_worker_stale_and_jobs_pending(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $health = app(QueueHealthService::class);

        $this->assertTrue($health->shouldWarnAdmin());
        $this->assertStringContainsString(
            'Background processor is not running',
            $health->adminStatus()['message'],
        );
    }

    public function test_no_admin_warning_when_worker_recently_processed(): void
    {
        app(QueueHealthService::class)->recordJobProcessed();

        $this->assertFalse(app(QueueHealthService::class)->shouldWarnAdmin());
    }

    public function test_admin_warning_when_batch_stuck_in_preparing(): void
    {
        ImportBatch::query()->create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'x.csv',
            'original_filename' => 'x.csv',
            'file_path' => 'imports/x.csv',
            'file_hash' => hash('sha256', 'x'),
            'row_count' => 1,
            'status' => 'completed',
            'source_type' => 'csv',
            'metadata' => ['materialization_status' => 'preparing'],
        ]);

        $this->assertTrue(app(QueueHealthService::class)->shouldWarnAdmin());
    }
}
