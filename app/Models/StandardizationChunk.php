<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StandardizationChunk extends Model
{
    protected $fillable = [
        'import_batch_id',
        'chunk_number',
        'start_row_number',
        'end_row_number',
        'status',
        'total_rows',
        'processed_rows',
        'auto_approved_rows',
        'review_required_rows',
        'skipped_rows',
        'rejected_rows',
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
