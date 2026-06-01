<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionContextSnapshot extends Model
{
    protected $fillable = [
        'prediction_id',
        'snapshot_hash',
        'snapshot_data',
        'stats_version',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_data' => 'array',
        ];
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class);
    }
}
