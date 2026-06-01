<?php

namespace App\Services\Import;

use App\Models\ImportBatch;
use Illuminate\Contracts\Queue\ShouldQueue;

class ImportJobDispatcher
{
    /**
     * Always queue pipeline work — never run jobs inside HTTP requests.
     */
    public function dispatch(ShouldQueue $job, ImportBatch $batch): void
    {
        dispatch($job);
    }

    public function processingMode(ImportBatch $batch): string
    {
        return 'async';
    }

    public function estimatedRowCount(ImportBatch $batch): int
    {
        if ($batch->row_count > 0) {
            return (int) $batch->row_count;
        }

        $metadata = $batch->metadata ?? [];

        return (int) ($metadata['data_row_count'] ?? $metadata['estimated_row_count'] ?? 0);
    }
}
