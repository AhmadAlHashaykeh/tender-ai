<?php

namespace App\Jobs;

use App\Models\ImportChunk;
use App\Services\Import\ImportChunkService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessImportChunkJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 3600;

    public function __construct(public ImportChunk $importChunk) {}

    public function handle(ImportChunkService $chunkService): void
    {
        $chunkService->processChunk($this->importChunk->fresh());
    }

    public function failed(?Throwable $exception): void
    {
        $chunk = $this->importChunk->fresh();

        if (! $chunk) {
            return;
        }

        $chunk->update([
            'status' => 'failed',
            'error_message' => $exception?->getMessage() ?? 'Chunk processing failed.',
            'completed_at' => now(),
        ]);

        $batch = $chunk->importBatch;

        if ($batch) {
            app(ImportChunkService::class)->checkBatchFinalization($batch->fresh());
        }
    }
}
