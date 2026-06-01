<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\Import\ImportBatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessImportBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public ImportBatch $importBatch) {}

    public function handle(ImportBatchService $importBatchService): void
    {
        $batch = $this->importBatch->fresh();

        if (! $batch) {
            return;
        }

        if ($batch->usesChunkedImport()) {
            return;
        }

        if ($batch->metadata['confirmed_mapping'] ?? null) {
            $importBatchService->dispatchChunkedImport($batch);

            return;
        }

        $importBatchService->process($batch);
    }

    public function failed(?Throwable $exception): void
    {
        $batch = $this->importBatch->fresh();

        if (! $batch) {
            return;
        }

        $batch->update([
            'status' => 'failed',
            'metadata' => array_merge($batch->metadata ?? [], [
                'failure_reason' => $exception?->getMessage() ?? 'Import processing failed.',
            ]),
            'completed_at' => now(),
        ]);
    }
}
