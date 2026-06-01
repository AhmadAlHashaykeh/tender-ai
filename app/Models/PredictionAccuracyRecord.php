<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionAccuracyRecord extends Model
{
    protected $fillable = [
        'prediction_id',
        'predicted_price',
        'actual_price',
        'price_error_pct',
        'won',
        'outcome_status',
        'metadata',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'won' => 'boolean',
            'evaluated_at' => 'datetime',
            'predicted_price' => 'decimal:6',
            'actual_price' => 'decimal:6',
            'price_error_pct' => 'decimal:4',
        ];
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class);
    }
}
