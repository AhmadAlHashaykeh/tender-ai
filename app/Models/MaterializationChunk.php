<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterializationChunk extends Model
{
    protected $fillable = [
        'import_batch_id',
        'chunk_number',
        'start_row_number',
        'end_row_number',
        'status',
        'retry_count',
        'total_rows',
        'processed_rows',
        'materialized_rows',
        'skipped_rows',
        'failed_rows',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
