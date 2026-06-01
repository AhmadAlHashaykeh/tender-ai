<?php

namespace App\Services\Import;

use App\Enums\ImportBatchStatus;
use App\Enums\StandardizationStatus;
use App\Jobs\RefreshImportStatisticsJob;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Services\Materialization\MaterializationChunkService;
use App\Services\Standardization\ImportRowStandardizationService;

class ImportPipelineOrchestratorService
{
    public function __construct(
        protected ImportJobDispatcher $jobDispatcher,
        protected ImportRowStandardizationService $standardizationService,
        protected MaterializationChunkService $materializationService,
        protected ImportPipelineReadinessService $readiness,
    ) {}

    public function pipelineAutomationEnabled(): bool
    {
        return (bool) config('import.pipeline_automation_enabled', true);
    }

    public function markProcessingMode(ImportBatch $batch): void
    {
        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'processing_mode' => $this->jobDispatcher->processingMode($batch),
                'pipeline_automation' => $this->pipelineAutomationEnabled(),
            ]),
        ]);
    }

    public function onImportValidationComplete(ImportBatch $batch): void
    {
        if (! $this->pipelineAutomationEnabled()) {
            return;
        }

        $batch = $batch->fresh();

        if (! $this->isValidationComplete($batch)) {
            return;
        }

        if ($this->standardizationAlreadyStarted($batch)) {
            return;
        }

        $this->startStandardization($batch);
    }

    public function onStandardizationComplete(ImportBatch $batch): void
    {
        if (! $this->pipelineAutomationEnabled()) {
            return;
        }

        $batch = $batch->fresh();
        $metadata = $batch->metadata ?? [];

        if (($metadata['standardization_status'] ?? '') !== 'completed') {
            return;
        }

        if ($this->reviewRequiredCount($batch) > 0) {
            return;
        }

        $this->startMaterialization($batch);
    }

    public function onReviewQueueUpdated(ImportBatch $batch): void
    {
        if (! $this->pipelineAutomationEnabled()) {
            return;
        }

        $batch = $batch->fresh();
        $metadata = $batch->metadata ?? [];

        if (($metadata['standardization_status'] ?? '') !== 'completed') {
            return;
        }

        if ($this->reviewRequiredCount($batch) > 0) {
            return;
        }

        if ($this->materializationAlreadyStarted($batch)) {
            if (($metadata['materialization_status'] ?? '') === 'completed') {
                $this->onMaterializationComplete($batch);
            }

            return;
        }

        $this->startMaterialization($batch);
    }

    public function onMaterializationComplete(ImportBatch $batch): void
    {
        if (! $this->pipelineAutomationEnabled()) {
            return;
        }

        $batch = $batch->fresh();
        $metadata = $batch->metadata ?? [];

        if (($metadata['materialization_status'] ?? '') !== 'completed') {
            return;
        }

        if ($this->readiness->batchIsPipelineReady($batch)) {
            return;
        }

        if (($metadata['statistics_status'] ?? '') === 'processing') {
            return;
        }

        if (! $this->readiness->batchRequiresMarketStatistics($batch)) {
            $this->markPipelineReady($batch, [
                'groups_processed' => 0,
                'pricing_statistics_created' => 0,
                'pricing_statistics_updated' => 0,
                'skipped' => true,
                'reason' => 'no_materialized_bid_records',
            ]);

            return;
        }

        $this->dispatchStatisticsRefresh($batch);
    }

    public function dispatchStatisticsRefresh(ImportBatch $batch): void
    {
        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'statistics_status' => 'processing',
                'statistics_started_at' => now()->toIso8601String(),
                'statistics_completed_at' => null,
                'statistics_last_error' => null,
                'pipeline_status' => 'preparing_statistics',
            ]),
        ]);

        $this->jobDispatcher->dispatch(new RefreshImportStatisticsJob($batch->id), $batch);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function onStatisticsRefreshComplete(ImportBatch $batch, array $summary): void
    {
        if (! $this->pipelineAutomationEnabled()) {
            return;
        }

        $batch = $batch->fresh();

        if (! $this->readiness->batchHasSufficientMarketStatistics($batch, $summary)) {
            $this->onStatisticsRefreshFailed(
                $batch,
                'No market statistics were generated for the materialized bid records in this upload.',
            );

            return;
        }

        $this->markPipelineReady($batch, $summary);
    }

    public function onStatisticsRefreshFailed(ImportBatch $batch, string $message): void
    {
        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'statistics_status' => 'failed',
                'statistics_completed_at' => now()->toIso8601String(),
                'statistics_last_error' => $message,
                'pipeline_status' => 'statistics_failed',
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function markPipelineReady(ImportBatch $batch, array $summary): void
    {
        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'statistics_status' => 'completed',
                'statistics_completed_at' => now()->toIso8601String(),
                'statistics_refresh_summary' => $summary,
                'statistics_refreshed_at' => now()->toIso8601String(),
                'statistics_last_error' => null,
                'pipeline_status' => 'ready',
                'pipeline_ready_at' => now()->toIso8601String(),
                'pricing_statistics_count' => $this->readiness->pricingStatisticsCountForBatch($batch),
            ]),
        ]);
    }

    public function startStandardization(ImportBatch $batch): void
    {
        $batch = $batch->fresh();

        $pending = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->where('standardization_status', StandardizationStatus::Pending->value)
            ->count();

        if ($pending === 0) {
            return;
        }

        if ($this->standardizationAlreadyStarted($batch)) {
            return;
        }

        $this->standardizationService->dispatchBatchJob($batch);
    }

    public function startMaterialization(ImportBatch $batch): void
    {
        $batch = $batch->fresh();
        $status = $batch->metadata['materialization_status'] ?? 'not_started';

        if (in_array($status, ['completed', 'failed'], true)) {
            return;
        }

        $eligible = app(\App\Services\Materialization\ImportMaterializationService::class)
            ->batchMaterializationStats($batch)['eligible_pending'];

        if ($eligible === 0) {
            $this->onMaterializationComplete($batch);

            return;
        }

        $this->materializationService->dispatchBatchJob($batch);
    }

    protected function isValidationComplete(ImportBatch $batch): bool
    {
        return in_array($batch->status, [
            ImportBatchStatus::Completed->value,
            ImportBatchStatus::CompletedWithErrors->value,
        ], true);
    }

    protected function standardizationAlreadyStarted(ImportBatch $batch): bool
    {
        $status = $batch->metadata['standardization_status'] ?? 'not_started';

        return in_array($status, ['processing', 'completed', 'failed'], true);
    }

    protected function materializationAlreadyStarted(ImportBatch $batch): bool
    {
        $status = $batch->metadata['materialization_status'] ?? 'not_started';

        return in_array($status, ['preparing', 'processing', 'completed', 'failed'], true);
    }

    protected function reviewRequiredCount(ImportBatch $batch): int
    {
        return ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->where('standardization_status', StandardizationStatus::ReviewRequired->value)
            ->count();
    }
}
