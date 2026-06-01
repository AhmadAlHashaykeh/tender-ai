<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportChunk extends Model
{
    protected $fillable = [
        'import_batch_id',
        'chunk_number',
        'start_row',
        'end_row',
        'status',
        'total_rows',
        'processed_rows',
        'valid_rows',
        'warning_rows',
        'invalid_rows',
        'duplicate_rows',
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
