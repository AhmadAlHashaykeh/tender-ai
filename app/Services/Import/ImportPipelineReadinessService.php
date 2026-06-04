<?php

namespace App\Services\Import;

use App\Models\BidRecord;
use App\Models\ImportBatch;
use App\Models\PricingStatistic;
use App\Services\Materialization\ImportMaterializationService;
use Illuminate\Support\Facades\DB;

class ImportPipelineReadinessService
{
    /**
     * Distinct drug × country groups from materialized bid records for this batch.
     */
    public function distinctDrugCountryGroupCount(ImportBatch $batch): int
    {
        $pairs = BidRecord::query()
            ->analyticsEligible()
            ->where('import_batch_id', $batch->id)
            ->whereNotNull('standardized_drug_id')
            ->whereNotNull('country_id')
            ->select('standardized_drug_id', 'country_id')
            ->distinct();

        return (int) DB::query()
            ->fromSub($pairs, 'distinct_drug_country_pairs')
            ->count();
    }

    public function pricingStatisticsCountForBatch(ImportBatch $batch): int
    {
        if ($this->distinctDrugCountryGroupCount($batch) === 0) {
            return 0;
        }

        return (int) PricingStatistic::query()
            ->whereNotNull('country_id')
            ->whereExists(function ($query) use ($batch) {
                $query->selectRaw('1')
                    ->from('bid_records')
                    ->where('import_batch_id', $batch->id)
                    ->whereColumn('bid_records.standardized_drug_id', 'pricing_statistics.standardized_drug_id')
                    ->whereColumn('bid_records.country_id', 'pricing_statistics.country_id');
            })
            ->count();
    }

    public function batchRequiresMarketStatistics(ImportBatch $batch): bool
    {
        return $this->distinctDrugCountryGroupCount($batch) > 0;
    }

    /**
     * @param  array<string, mixed>|null  $summary
     */
    public function batchHasSufficientMarketStatistics(ImportBatch $batch, ?array $summary = null): bool
    {
        if (! $this->batchRequiresMarketStatistics($batch)) {
            return true;
        }

        if ($this->pricingStatisticsCountForBatch($batch) > 0) {
            return true;
        }

        if ($summary !== null) {
            $created = (int) ($summary['pricing_statistics_created'] ?? 0);
            $updated = (int) ($summary['pricing_statistics_updated'] ?? 0);

            return ($created + $updated) > 0;
        }

        return false;
    }

    public function batchIsPipelineReady(ImportBatch $batch): bool
    {
        $metadata = $batch->metadata ?? [];

        if (in_array($metadata['materialization_status'] ?? '', ['incomplete', 'preparing', 'processing'], true)) {
            return false;
        }

        if (app(ImportMaterializationService::class)->batchMaterializationStats($batch)['eligible_pending'] > 0) {
            return false;
        }

        if (($metadata['pipeline_status'] ?? '') !== 'ready') {
            return false;
        }

        if (($metadata['pipeline_ready_at'] ?? null) === null) {
            return false;
        }

        if (($metadata['statistics_status'] ?? '') === 'failed') {
            return false;
        }

        if ($this->batchRequiresMarketStatistics($batch)
            && ! $this->batchHasSufficientMarketStatistics($batch)) {
            return false;
        }

        return true;
    }

    /**
     * Context for AI recommendation create page messaging.
     *
     * @return array{
     *     pricing_statistics_count: int,
     *     has_any_import: bool,
     *     imports_processing: bool,
     *     statistics_failed: bool,
     *     message: string,
     *     message_type: string
     * }
     */
    public function recommendationAvailabilityContext(): array
    {
        $pricingStatsCount = (int) PricingStatistic::query()->count();
        $hasAnyImport = ImportBatch::query()
            ->whereNotIn('status', ['uploaded', 'awaiting_mapping', 'failed'])
            ->exists();

        $importsProcessing = ImportBatch::query()
            ->whereNotIn('status', ['failed', 'uploaded', 'awaiting_mapping'])
            ->where(function ($query) {
                $query->whereIn('status', ['queued', 'processing', 'parsing', 'validating'])
                    ->orWhere('metadata->pipeline_status', '!=', 'ready')
                    ->orWhereNull('metadata->pipeline_ready_at')
                    ->orWhere('metadata->statistics_status', 'processing')
                    ->orWhereIn('metadata->materialization_status', ['preparing', 'processing'])
                    ->orWhereIn('metadata->standardization_status', ['processing']);
            })
            ->exists();

        $batchesAwaitingStatistics = $this->countBatchesAwaitingMarketStatistics();
        $importsWithoutAnalyticsBids = $this->hasCompletedImportsWithoutAnalyticsBids();

        $statisticsFailed = ImportBatch::query()
            ->where('metadata->statistics_status', 'failed')
            ->exists();

        $messageType = 'none';
        $message = '';

        if ($pricingStatsCount > 0) {
            $messageType = 'ready';
        } elseif ($importsProcessing || $batchesAwaitingStatistics > 0) {
            $messageType = 'processing';
            $message = 'Your uploaded data is still being prepared. Recommendations will be available shortly.';
            if ($batchesAwaitingStatistics > 0 && config('queue.default') === 'database') {
                $message .= ' If this persists, open your upload details and run pending processing or retry market statistics.';
            }
        } elseif ($statisticsFailed) {
            $messageType = 'failed';
            $message = 'Market analysis preparation failed on a recent upload. Open the uploaded file and retry market statistics.';
        } elseif ($importsWithoutAnalyticsBids) {
            $messageType = 'no_analytics_bids';
            $message = 'Uploads were processed but no analytics-ready bid records exist yet. Complete product matching and materialization from the upload details page.';
        } elseif (! $hasAnyImport) {
            $messageType = 'no_data';
            $message = 'Upload tender data through the import hub to enable price recommendations.';
        } else {
            $messageType = 'no_stats';
            $message = 'Market statistics are not available yet. Complete an import or retry market statistics from the upload details page.';
        }

        return [
            'pricing_statistics_count' => $pricingStatsCount,
            'has_any_import' => $hasAnyImport,
            'imports_processing' => $importsProcessing || $batchesAwaitingStatistics > 0,
            'batches_awaiting_statistics' => $batchesAwaitingStatistics,
            'statistics_failed' => $statisticsFailed,
            'message' => $message,
            'message_type' => $messageType,
        ];
    }

    /**
     * Completed materialization with bid groups but insufficient pricing_statistics for that batch.
     */
    public function countBatchesAwaitingMarketStatistics(): int
    {
        return ImportBatch::query()
            ->whereNotIn('status', ['failed', 'uploaded', 'awaiting_mapping'])
            ->where('metadata->materialization_status', 'completed')
            ->get()
            ->filter(function (ImportBatch $batch) {
                if (($batch->metadata['statistics_status'] ?? '') === 'failed') {
                    return false;
                }

                return $this->batchRequiresMarketStatistics($batch)
                    && ! $this->batchHasSufficientMarketStatistics($batch);
            })
            ->count();
    }

    public function hasCompletedImportsWithoutAnalyticsBids(): bool
    {
        $completedBatches = ImportBatch::query()
            ->whereNotIn('status', ['failed', 'uploaded', 'awaiting_mapping'])
            ->where('metadata->materialization_status', 'completed')
            ->pluck('id');

        if ($completedBatches->isEmpty()) {
            return false;
        }

        $hasAnyBid = BidRecord::query()->whereIn('import_batch_id', $completedBatches)->exists();

        if (! $hasAnyBid) {
            return true;
        }

        return ! BidRecord::query()
            ->analyticsEligible()
            ->whereIn('import_batch_id', $completedBatches)
            ->exists();
    }
}
