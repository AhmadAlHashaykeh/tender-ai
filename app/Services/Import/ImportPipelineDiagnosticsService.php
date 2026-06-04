<?php

namespace App\Services\Import;

use App\Enums\StandardizationStatus;
use App\Models\BidRecord;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\PricingStatistic;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Models\TenderItem;
use App\Services\Materialization\ImportMaterializationService;
use Illuminate\Support\Facades\DB;

class ImportPipelineDiagnosticsService
{
    public function __construct(
        protected ImportBatchStatsService $batchStats,
        protected ImportMaterializationService $materializationService,
        protected ImportPipelineReadinessService $readiness,
        protected ImportCountryDiagnosticsService $countryDiagnostics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forBatch(ImportBatch $batch): array
    {
        $batch = $batch->fresh();
        $metadata = $batch->metadata ?? [];
        $rowCounts = $this->batchStats->rowCounts($batch->id, $batch);
        $materialization = $this->materializationService->batchMaterializationStats($batch);

        $rowsTotal = (int) ($rowCounts['total'] ?? 0);
        $rowsProcessed = (int) $batch->processed_count;
        $rowsStandardized = $this->countStandardizedRows($batch->id);
        $matchesPending = (int) ($rowCounts['standardization_review'] ?? 0)
            + (int) ($rowCounts['standardization_pending'] ?? 0);
        $matchesApproved = (int) ($rowCounts['standardization_auto_approved'] ?? 0)
            + $this->countByStandardizationStatus($batch->id, StandardizationStatus::Approved->value);

        $bidRecordsTotal = (int) BidRecord::query()->where('import_batch_id', $batch->id)->count();
        $bidRecordsAnalytics = (int) BidRecord::query()
            ->analyticsEligible()
            ->where('import_batch_id', $batch->id)
            ->count();

        $marketStatsCount = $this->readiness->pricingStatisticsCountForBatch($batch);
        $drugCountryGroups = $this->readiness->distinctDrugCountryGroupCount($batch);

        $entitiesMaterialized = ($metadata['materialization_status'] ?? '') === 'completed'
            && $bidRecordsTotal > 0;

        $pendingJobs = $this->pendingJobsForBatch($batch->id);
        $failedJobs = $this->recentFailedJobsForBatch($batch->id);

        $lastError = $metadata['statistics_last_error']
            ?? $metadata['materialization_last_error']
            ?? $metadata['standardization_last_error']
            ?? $metadata['failure_reason']
            ?? ($failedJobs[0]['exception'] ?? null);

        return [
            'rows_uploaded' => $rowsTotal,
            'rows_processed' => $rowsProcessed > 0 ? $rowsProcessed : $rowsTotal,
            'rows_standardized' => $rowsStandardized,
            'product_matches_pending' => $matchesPending,
            'product_matches_approved' => $matchesApproved,
            'entities_materialized' => $entitiesMaterialized,
            'bid_records_created' => $bidRecordsTotal,
            'bid_records_analytics_eligible' => $bidRecordsAnalytics,
            'drug_country_groups' => $drugCountryGroups,
            'market_statistics_records' => $marketStatsCount,
            'materialized_rows' => (int) ($materialization['materialized'] ?? 0),
            'eligible_pending_materialization' => (int) ($materialization['eligible_pending'] ?? 0),
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => count($failedJobs),
            'failed_job_details' => $failedJobs,
            'last_error' => $lastError,
            'pipeline_status' => $metadata['pipeline_status'] ?? null,
            'statistics_status' => $metadata['statistics_status'] ?? null,
            'materialization_status' => $metadata['materialization_status'] ?? null,
            'standardization_status' => $metadata['standardization_status'] ?? null,
            'pipeline_ready' => $this->readiness->batchIsPipelineReady($batch),
            'requires_market_statistics' => $this->readiness->batchRequiresMarketStatistics($batch),
            'global_pricing_statistics' => (int) PricingStatistic::query()->count(),
            'related_entities' => [
                'drugs' => (int) StandardizedDrug::query()
                    ->whereIn('id', BidRecord::query()
                        ->where('import_batch_id', $batch->id)
                        ->whereNotNull('standardized_drug_id')
                        ->distinct()
                        ->pluck('standardized_drug_id'))
                    ->count(),
                'companies' => (int) Company::query()
                    ->whereIn('id', BidRecord::query()
                        ->where('import_batch_id', $batch->id)
                        ->whereNotNull('company_id')
                        ->distinct()
                        ->pluck('company_id'))
                    ->count(),
                'tenders' => (int) Tender::query()
                    ->whereIn('id', BidRecord::query()
                        ->where('import_batch_id', $batch->id)
                        ->whereNotNull('tender_id')
                        ->distinct()
                        ->pluck('tender_id'))
                    ->count(),
                'tender_items' => (int) TenderItem::query()
                    ->whereIn('id', BidRecord::query()
                        ->where('import_batch_id', $batch->id)
                        ->whereNotNull('tender_item_id')
                        ->distinct()
                        ->pluck('tender_item_id'))
                    ->count(),
            ],
            'queue_connection' => config('queue.default'),
            'queue_pending_total' => $this->usesDatabaseQueue()
                ? (int) DB::table('jobs')->count()
                : 0,
            'country_mapping' => $this->countryDiagnostics->summaryForBatch($batch),
        ];
    }

    protected function countStandardizedRows(int $batchId): int
    {
        return (int) ImportRow::query()
            ->where('import_batch_id', $batchId)
            ->whereNotIn('standardization_status', [
                StandardizationStatus::Pending->value,
            ])
            ->count();
    }

    protected function countByStandardizationStatus(int $batchId, string $status): int
    {
        return (int) ImportRow::query()
            ->where('import_batch_id', $batchId)
            ->where('standardization_status', $status)
            ->count();
    }

    protected function pendingJobsForBatch(int $batchId): int
    {
        if (! $this->usesDatabaseQueue()) {
            return 0;
        }

        $needle = '"importBatchId":'.$batchId;

        return (int) DB::table('jobs')
            ->where('payload', 'like', '%'.$needle.'%')
            ->count();
    }

    /**
     * @return list<array{uuid: string, queue: string, failed_at: string, exception: string}>
     */
    protected function recentFailedJobsForBatch(int $batchId, int $limit = 3): array
    {
        if (! $this->tableExists('failed_jobs')) {
            return [];
        }

        $needle = '"importBatchId":'.$batchId;

        return DB::table('failed_jobs')
            ->where('payload', 'like', '%'.$needle.'%')
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'uuid' => (string) $row->uuid,
                'queue' => (string) $row->queue,
                'failed_at' => (string) $row->failed_at,
                'exception' => $this->truncateException((string) $row->exception),
            ])
            ->all();
    }

    protected function truncateException(string $exception): string
    {
        $line = strtok($exception, "\n") ?: $exception;

        return strlen($line) > 240 ? substr($line, 0, 237).'...' : $line;
    }

    protected function usesDatabaseQueue(): bool
    {
        return config('queue.default') === 'database';
    }

    protected function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }
}
