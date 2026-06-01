<?php

namespace Tests\Feature;

use App\Jobs\RefreshImportStatisticsJob;
use App\Models\BidRecord;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\ImportBatch;
use App\Models\PricingStatistic;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Models\TenderItem;
use App\Models\User;
use App\Services\Import\ImportPipelineOrchestratorService;
use App\Services\Import\ImportPipelineReadinessService;
use App\Services\Statistics\StatisticsRefreshService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ImportStatisticsPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RegionSeeder::class,
            CurrencySeeder::class,
            CountrySeeder::class,
        ]);

        config(['import.pipeline_automation_enabled' => true]);
    }

    public function test_materialization_complete_dispatches_statistics_job(): void
    {
        Queue::fake();

        $batch = $this->batchWithMaterializationCompleted();
        $this->seedBidRecordForBatch($batch);

        app(ImportPipelineOrchestratorService::class)->onMaterializationComplete($batch);

        Queue::assertPushed(RefreshImportStatisticsJob::class, fn (RefreshImportStatisticsJob $job) => $job->importBatchId === $batch->id);

        $batch->refresh();
        $this->assertSame('processing', $batch->metadata['statistics_status'] ?? null);
        $this->assertNull($batch->metadata['pipeline_ready_at'] ?? null);
    }

    public function test_statistics_completion_marks_pipeline_ready_when_statistics_exist(): void
    {
        $batch = $this->batchWithMaterializationCompleted();
        $this->seedBidRecordForBatch($batch);

        $summary = app(StatisticsRefreshService::class)->refreshForImportBatch($batch);

        app(ImportPipelineOrchestratorService::class)->onStatisticsRefreshComplete($batch->fresh(), $summary);

        $batch->refresh();
        $this->assertSame('ready', $batch->metadata['pipeline_status'] ?? null);
        $this->assertNotNull($batch->metadata['pipeline_ready_at'] ?? null);
        $this->assertTrue(app(ImportPipelineReadinessService::class)->batchIsPipelineReady($batch));
        $this->assertGreaterThan(0, PricingStatistic::query()->count());
    }

    public function test_ready_state_not_set_when_statistics_refresh_produces_nothing(): void
    {
        $batch = $this->batchWithMaterializationCompleted();
        $this->seedBidRecordForBatch($batch);

        app(ImportPipelineOrchestratorService::class)->onStatisticsRefreshComplete($batch, [
            'groups_processed' => 0,
            'pricing_statistics_created' => 0,
            'pricing_statistics_updated' => 0,
        ]);

        $batch->refresh();
        $this->assertSame('statistics_failed', $batch->metadata['pipeline_status'] ?? null);
        $this->assertNull($batch->metadata['pipeline_ready_at'] ?? null);
        $this->assertFalse(app(ImportPipelineReadinessService::class)->batchIsPipelineReady($batch));
    }

    public function test_failed_statistics_can_retry_via_http(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $batch = $this->batchWithMaterializationCompleted();
        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'statistics_status' => 'failed',
                'pipeline_status' => 'statistics_failed',
                'statistics_last_error' => 'Test failure',
            ]),
        ]);

        $this->actingAs($user)
            ->post(route('imports.statistics.retry', $batch))
            ->assertRedirect(route('imports.show', $batch));

        Queue::assertPushed(RefreshImportStatisticsJob::class);
    }

    public function test_refresh_import_statistics_job_does_not_require_cli(): void
    {
        $batch = $this->batchWithMaterializationCompleted();
        $this->seedBidRecordForBatch($batch);

        $job = new RefreshImportStatisticsJob($batch->id);
        $job->handle(
            app(StatisticsRefreshService::class),
            app(ImportPipelineOrchestratorService::class),
        );

        $this->assertGreaterThan(0, PricingStatistic::query()->count());
        $this->assertTrue(app(ImportPipelineReadinessService::class)->batchIsPipelineReady($batch->fresh()));
    }

    public function test_recommendation_page_shows_processing_message_when_imports_incomplete(): void
    {
        $user = User::factory()->create();

        ImportBatch::query()->create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'in-progress.csv',
            'original_filename' => 'in-progress.csv',
            'file_path' => 'imports/in-progress.csv',
            'file_hash' => hash('sha256', 'in-progress'),
            'status' => 'completed',
            'source_type' => 'csv',
            'metadata' => [
                'materialization_status' => 'completed',
                'statistics_status' => 'processing',
                'pipeline_status' => 'preparing_statistics',
            ],
        ]);

        $this->actingAs($user)
            ->get(route('ai.recommendations.create'))
            ->assertOk()
            ->assertSee('still being prepared', false);
    }

    protected function batchWithMaterializationCompleted(): ImportBatch
    {
        return ImportBatch::query()->create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'stats-test.csv',
            'original_filename' => 'stats-test.csv',
            'file_path' => 'imports/stats-test.csv',
            'file_hash' => hash('sha256', 'stats-test'),
            'row_count' => 1,
            'status' => 'completed',
            'source_type' => 'csv',
            'metadata' => [
                'materialization_status' => 'completed',
                'standardization_status' => 'completed',
            ],
        ]);
    }

    protected function seedBidRecordForBatch(ImportBatch $batch): BidRecord
    {
        $country = Country::query()->firstOrFail();
        $currency = Currency::query()->where('code', 'USD')->firstOrFail();
        $drug = StandardizedDrug::query()->create([
            'code' => 'STAT-'.uniqid(),
            'inn' => 'Test Inn',
            'display_name' => 'Test Drug',
            'is_active' => true,
            'source' => 'test',
        ]);
        $company = Company::query()->create([
            'name' => 'Stats Co',
            'normalized_name' => 'stats co',
            'is_active' => true,
            'source' => 'test',
        ]);
        $tender = Tender::query()->create([
            'tender_number' => 'T-STATS-'.uniqid(),
            'country_id' => $country->id,
            'year' => 2024,
            'status' => 'active',
        ]);
        $item = TenderItem::query()->create([
            'tender_id' => $tender->id,
            'standardized_drug_id' => $drug->id,
        ]);

        return BidRecord::query()->create([
            'import_batch_id' => $batch->id,
            'tender_item_id' => $item->id,
            'tender_id' => $tender->id,
            'standardized_drug_id' => $drug->id,
            'country_id' => $country->id,
            'company_id' => $company->id,
            'currency_id' => $currency->id,
            'bid_status' => 'awarded',
            'is_winner' => true,
            'row_type' => 'winning_bid',
            'price_usd' => 12.50,
            'quantity' => 100,
            'award_year' => 2024,
            'is_analytics_ready' => true,
            'excluded_from_stats' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
