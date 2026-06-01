<?php

namespace App\Jobs;

use App\Models\MaterializationChunk;
use App\Services\Materialization\MaterializationChunkService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class MaterializeImportChunkJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 3600;

    public function __construct(public MaterializationChunk $materializationChunk) {}

    public function handle(MaterializationChunkService $chunkService): void
    {
        $chunkService->processChunk($this->materializationChunk->fresh());
    }

    public function failed(?Throwable $exception): void
    {
        $chunk = $this->materializationChunk->fresh();

        if (! $chunk) {
            return;
        }

        $chunk->update([
            'status' => 'failed',
            'error_message' => $exception?->getMessage() ?? 'Materialization chunk failed.',
            'completed_at' => now(),
        ]);

        $batch = $chunk->importBatch;

        if ($batch) {
            app(MaterializationChunkService::class)->checkBatchFinalization($batch->fresh());
        }
    }
}
