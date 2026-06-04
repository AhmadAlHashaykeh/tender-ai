<?php

namespace Tests\Feature;

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
use App\Services\Import\ImportBatchPipelineAdvanceService;
use App\Services\Import\ImportPipelineOrchestratorService;
use App\Services\Import\ImportPipelineReadinessService;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportPipelineMarketStatisticsRecoveryTest extends TestCase
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

    public function test_retry_statistics_sync_succeeds_when_bid_records_exist(): void
    {
        $user = User::factory()->create();
        $batch = $this->batchWithMaterializationCompleted();
        $this->seedBidRecordForBatch($batch);

        $this->actingAs($user)
            ->post(route('imports.statistics.retry', $batch))
            ->assertRedirect(route('imports.show', $batch))
            ->assertSessionHas('success');

        $this->assertGreaterThan(0, PricingStatistic::query()->count());
        $this->assertTrue(app(ImportPipelineReadinessService::class)->batchIsPipelineReady($batch->fresh()));
    }

    public function test_retry_statistics_returns_clear_error_without_bid_records(): void
    {
        $user = User::factory()->create();
        $batch = $this->batchWithMaterializationCompleted();

        $this->actingAs($user)
            ->post(route('imports.statistics.retry', $batch))
            ->assertRedirect(route('imports.show', $batch))
            ->assertSessionHasErrors('statistics');

        $this->assertStringContainsString(
            'no bid records',
            session('errors')->first('statistics'),
        );
    }

    public function test_import_show_displays_pipeline_diagnostics_card(): void
    {
        $user = User::factory()->create();
        $batch = $this->batchWithMaterializationCompleted();

        $this->actingAs($user)
            ->get(route('imports.show', $batch))
            ->assertOk()
            ->assertSee('Import Pipeline Status', false)
            ->assertSee('Retry Market Statistics', false)
            ->assertSee('Run Pending Processing', false);
    }

    public function test_recommendation_page_processing_when_batch_awaiting_statistics(): void
    {
        $user = User::factory()->create();
        $batch = $this->batchWithMaterializationCompleted();
        $this->seedBidRecordForBatch($batch);

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'pipeline_status' => 'ready',
                'pipeline_ready_at' => now()->toIso8601String(),
                'statistics_status' => 'completed',
            ]),
        ]);

        $this->assertSame(0, PricingStatistic::query()->count());

        $context = app(ImportPipelineReadinessService::class)->recommendationAvailabilityContext();

        $this->assertSame('processing', $context['message_type']);
        $this->assertGreaterThan(0, $context['batches_awaiting_statistics']);

        $this->actingAs($user)
            ->get(route('ai.recommendations.create'))
            ->assertOk()
            ->assertSee('still being prepared', false);
    }

    public function test_materialization_complete_dispatches_statistics_job(): void
    {
        Queue::fake();

        $batch = $this->batchWithMaterializationCompleted();
        $this->seedBidRecordForBatch($batch);

        app(ImportPipelineOrchestratorService::class)->onMaterializationComplete($batch);

        Queue::assertPushed(\App\Jobs\RefreshImportStatisticsJob::class);
    }

    public function test_advance_service_retry_market_statistics_without_queue(): void
    {
        $batch = $this->batchWithMaterializationCompleted();
        $this->seedBidRecordForBatch($batch);

        $result = app(ImportBatchPipelineAdvanceService::class)->retryMarketStatisticsSync($batch);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, PricingStatistic::query()->count());
    }

    protected function batchWithMaterializationCompleted(): ImportBatch
    {
        return ImportBatch::query()->create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'recovery.csv',
            'original_filename' => 'recovery.csv',
            'file_path' => 'imports/recovery.csv',
            'file_hash' => hash('sha256', 'recovery'),
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
            'code' => 'REC-'.uniqid(),
            'inn' => 'Test Inn',
            'display_name' => 'Test Drug',
            'is_active' => true,
            'source' => 'test',
        ]);
        $company = Company::query()->create([
            'name' => 'Recovery Co',
            'normalized_name' => 'recovery co',
            'is_active' => true,
            'source' => 'test',
        ]);
        $tender = Tender::query()->create([
            'tender_number' => 'T-REC-'.uniqid(),
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
}
