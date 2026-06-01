<?php

namespace App\Services\Import;

use App\Models\BidRecord;
use App\Models\ImportBatch;
use App\Models\PricingStatistic;
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

        if (($metadata['pipeline_status'] ?? '') !== 'ready') {
            return false;
        }

        if (($metadata['pipeline_ready_at'] ?? null) === null) {
            return false;
        }

        if (($metadata['statistics_status'] ?? '') === 'failed') {
            return false;
        }

        return $this->batchHasSufficientMarketStatistics($batch);
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
            ->where(function ($query) {
                $query->where('metadata->pipeline_status', '!=', 'ready')
                    ->orWhereNull('metadata->pipeline_ready_at');
            })
            ->whereNotIn('status', ['failed', 'uploaded', 'awaiting_mapping'])
            ->exists();

        $statisticsFailed = ImportBatch::query()
            ->where('metadata->statistics_status', 'failed')
            ->exists();

        $messageType = 'none';
        $message = '';

        if ($pricingStatsCount > 0) {
            $messageType = 'ready';
        } elseif ($importsProcessing) {
            $messageType = 'processing';
            $message = 'Your uploaded data is still being prepared. Recommendations will be available shortly.';
        } elseif ($statisticsFailed) {
            $messageType = 'failed';
            $message = 'Market analysis preparation failed on a recent upload. Open the uploaded file and retry market statistics.';
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
            'imports_processing' => $importsProcessing,
            'statistics_failed' => $statisticsFailed,
            'message' => $message,
            'message_type' => $messageType,
        ];
    }
}
