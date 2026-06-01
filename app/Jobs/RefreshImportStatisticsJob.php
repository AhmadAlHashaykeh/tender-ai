<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\Import\ImportPipelineOrchestratorService;
use App\Services\Statistics\StatisticsRefreshService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RefreshImportStatisticsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 3;

    public function __construct(public int $importBatchId) {}

    public function handle(
        StatisticsRefreshService $statisticsRefreshService,
        ImportPipelineOrchestratorService $orchestrator,
    ): void {
        $batch = ImportBatch::query()->findOrFail($this->importBatchId);

        try {
            $summary = $statisticsRefreshService->refreshForImportBatch($batch);
            $orchestrator->onStatisticsRefreshComplete($batch->fresh(), $summary);
        } catch (Throwable $exception) {
            $orchestrator->onStatisticsRefreshFailed($batch->fresh(), $exception->getMessage());
        }
    }

    public function failed(?Throwable $exception): void
    {
        $batch = ImportBatch::query()->find($this->importBatchId);

        if (! $batch) {
            return;
        }

        app(ImportPipelineOrchestratorService::class)->onStatisticsRefreshFailed(
            $batch,
            $exception?->getMessage() ?? 'Market statistics job failed.',
        );
    }
}
