<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\Materialization\MaterializationChunkService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class MaterializeImportBatchJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 3;

    public function __construct(public int $importBatchId) {}

    public function handle(MaterializationChunkService $chunkService): void
    {
        $batch = ImportBatch::query()->findOrFail($this->importBatchId);

        $chunkService->resumeOrOrchestrate($batch);
    }

    public function failed(?Throwable $exception): void
    {
        $batch = ImportBatch::query()->find($this->importBatchId);

        if (! $batch) {
            return;
        }

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'materialization_status' => 'failed',
                'materialization_completed_at' => now()->toIso8601String(),
                'materialization_last_error' => $exception?->getMessage() ?? 'Materialization job failed.',
            ]),
        ]);
    }
}
