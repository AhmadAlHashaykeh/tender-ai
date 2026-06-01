<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\Standardization\StandardizationChunkService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class StandardizeImportBatchJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 3;

    public function __construct(public int $importBatchId) {}

    public function handle(StandardizationChunkService $chunkService): void
    {
        $batch = ImportBatch::query()->findOrFail($this->importBatchId);

        if ($batch->usesChunkedStandardization()) {
            return;
        }

        $chunkService->orchestrate($batch);
    }

    public function failed(?Throwable $exception): void
    {
        $batch = ImportBatch::query()->find($this->importBatchId);

        if (! $batch) {
            return;
        }

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'standardization_status' => 'failed',
                'standardization_completed_at' => now()->toIso8601String(),
                'standardization_last_error' => $exception?->getMessage() ?? 'Standardization job failed.',
            ]),
        ]);
    }
}
