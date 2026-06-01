<?php

namespace App\Jobs;

use App\Models\StandardizationChunk;
use App\Services\Standardization\StandardizationChunkService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class StandardizeImportChunkJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 3600;

    public function __construct(public StandardizationChunk $standardizationChunk) {}

    public function handle(StandardizationChunkService $chunkService): void
    {
        $chunkService->processChunk($this->standardizationChunk->fresh());
    }

    public function failed(?Throwable $exception): void
    {
        $chunk = $this->standardizationChunk->fresh();

        if (! $chunk) {
            return;
        }

        $chunk->update([
            'status' => 'failed',
            'error_message' => $exception?->getMessage() ?? 'Standardization chunk failed.',
            'completed_at' => now(),
        ]);

        $batch = $chunk->importBatch;

        if ($batch) {
            app(StandardizationChunkService::class)->checkBatchFinalization($batch->fresh());
        }
    }
}
