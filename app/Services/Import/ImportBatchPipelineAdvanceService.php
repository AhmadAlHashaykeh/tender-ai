<?php

namespace App\Services\Import;

use App\Enums\ImportBatchStatus;
use App\Models\BidRecord;
use App\Models\ImportBatch;
use App\Services\Materialization\MaterializationChunkService;
use App\Services\Statistics\StatisticsRefreshService;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class ImportBatchPipelineAdvanceService
{
    public function __construct(
        protected ImportPipelineOrchestratorService $orchestrator,
        protected ImportPipelineReadinessService $readiness,
        protected StatisticsRefreshService $statisticsRefresh,
        protected MaterializationChunkService $materializationChunks,
    ) {}

    /**
     * Safely advance queued work for one batch (shared-hosting recovery).
     *
     * @return array{processed_jobs: int, actions: list<string>, message: string}
     */
    public function runPendingProcessing(ImportBatch $batch, int $maxJobs = 15): array
    {
        $actions = $this->recoverBatchPipeline($batch->fresh());

        $processedJobs = 0;

        if (config('queue.default') === 'database') {
            Artisan::call('queue:process-pending', [
                '--max-jobs' => max(1, $maxJobs),
                '--timeout' => 120,
            ]);
            $output = trim(Artisan::output());
            if (preg_match('/Processed (\d+) queue job/', $output, $matches)) {
                $processedJobs = (int) $matches[1];
            }
        }

        $batch = $batch->fresh();
        $this->orchestrator->onReviewQueueUpdated($batch);
        $this->orchestrator->onMaterializationComplete($batch->fresh());

        return [
            'processed_jobs' => $processedJobs,
            'actions' => $actions,
            'message' => $this->buildAdvanceMessage($batch->fresh(), $processedJobs, $actions),
        ];
    }

    /**
     * Run market statistics synchronously for a batch (HTTP retry action).
     *
     * @return array{success: bool, message: string, summary?: array<string, mixed>}
     */
    public function retryMarketStatisticsSync(ImportBatch $batch): array
    {
        $batch = $batch->fresh();
        $metadata = $batch->metadata ?? [];

        if (($metadata['materialization_status'] ?? '') !== 'completed') {
            return [
                'success' => false,
                'message' => 'Cannot refresh statistics because data preparation has not completed. Finish product matching and materialization first.',
            ];
        }

        $bidRecordsTotal = (int) BidRecord::query()->where('import_batch_id', $batch->id)->count();

        if ($bidRecordsTotal === 0) {
            return [
                'success' => false,
                'message' => 'Cannot refresh statistics because no bid records were created. Please complete product matching and materialization first.',
            ];
        }

        $analyticsCount = (int) BidRecord::query()
            ->analyticsEligible()
            ->where('import_batch_id', $batch->id)
            ->count();

        if ($analyticsCount === 0) {
            return [
                'success' => false,
                'message' => 'Bid records exist but none are analytics-ready (awarded winner with price USD). Review uploaded rows and product matching, then materialize again.',
            ];
        }

        if (! $this->readiness->batchRequiresMarketStatistics($batch)) {
            $this->orchestrator->onMaterializationComplete($batch);

            return [
                'success' => true,
                'message' => 'No drug × country groups require market statistics for this upload.',
            ];
        }

        $batch->update([
            'metadata' => array_merge($metadata, [
                'pipeline_ready_at' => null,
                'pipeline_status' => 'preparing_statistics',
                'statistics_status' => 'processing',
                'statistics_started_at' => now()->toIso8601String(),
                'statistics_last_error' => null,
            ]),
        ]);

        try {
            $summary = $this->statisticsRefresh->refreshForImportBatch($batch->fresh());
            $this->orchestrator->onStatisticsRefreshComplete($batch->fresh(), $summary);

            $batch = $batch->fresh();

            if ($this->readiness->batchIsPipelineReady($batch)) {
                return [
                    'success' => true,
                    'message' => 'Market statistics refreshed successfully. Predictions are ready.',
                    'summary' => $summary,
                ];
            }

            return [
                'success' => false,
                'message' => $batch->metadata['statistics_last_error']
                    ?? 'Market statistics refresh completed but no pricing statistics were stored for this upload.',
                'summary' => $summary,
            ];
        } catch (Throwable $exception) {
            $this->orchestrator->onStatisticsRefreshFailed($batch->fresh(), $exception->getMessage());

            return [
                'success' => false,
                'message' => 'Market statistics refresh failed: '.$exception->getMessage(),
            ];
        }
    }

    /**
     * @return list<string>
     */
    public function recoverBatchPipeline(ImportBatch $batch): array
    {
        $actions = [];
        $batch = $batch->fresh();
        $metadata = $batch->metadata ?? [];

        if ($this->isImportInProgress($batch)) {
            return $actions;
        }

        if ($this->orchestrator->pipelineAutomationEnabled()) {
            $this->orchestrator->onImportValidationComplete($batch);
            $actions[] = 'checked_import_validation';
        }

        $batch = $batch->fresh();
        $metadata = $batch->metadata ?? [];

        if (($metadata['standardization_status'] ?? '') === 'completed') {
            $matStatus = $metadata['materialization_status'] ?? 'not_started';

            if (in_array($matStatus, ['not_started', 'preparing'], true)) {
                $this->materializationChunks->resumeOrOrchestrate($batch);
                $actions[] = 'materialization_orchestrated';
            } elseif (in_array($matStatus, ['processing'], true)) {
                $this->materializationChunks->resumeOrOrchestrate($batch);
                $actions[] = 'materialization_resumed';
            }
        }

        $batch = $batch->fresh();
        $metadata = $batch->metadata ?? [];

        if (($metadata['materialization_status'] ?? '') === 'completed') {
            $statsStatus = $metadata['statistics_status'] ?? 'not_started';

            if ($this->readiness->batchRequiresMarketStatistics($batch)
                && ! $this->readiness->batchHasSufficientMarketStatistics($batch)
                && ! in_array($statsStatus, ['processing'], true)) {
                $this->orchestrator->dispatchStatisticsRefresh($batch);
                $actions[] = 'statistics_refresh_dispatched';
            }
        }

        return $actions;
    }

    /**
     * @param  list<string>  $actions
     */
    protected function buildAdvanceMessage(ImportBatch $batch, int $processedJobs, array $actions): string
    {
        if ($this->readiness->batchIsPipelineReady($batch)) {
            return 'Pipeline is ready for predictions.'
                .($processedJobs > 0 ? " Processed {$processedJobs} background job(s)." : '');
        }

        if ($processedJobs > 0) {
            return "Processed {$processedJobs} background job(s). Pipeline is still in progress — refresh this page shortly.";
        }

        if ($actions !== []) {
            return 'Queued the next pipeline step(s). Ensure server cron runs `php artisan schedule:run` every minute, or use Retry Market Statistics if preparation is already complete.';
        }

        return 'No pending pipeline steps were found for this upload. Review the pipeline status card below.';
    }

    protected function isImportInProgress(ImportBatch $batch): bool
    {
        return in_array($batch->status, [
            ImportBatchStatus::Queued->value,
            ImportBatchStatus::Processing->value,
            ImportBatchStatus::Parsing->value,
            ImportBatchStatus::Validating->value,
        ], true);
    }
}
