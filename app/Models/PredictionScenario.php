<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionScenario extends Model
{
    protected $fillable = [
        'prediction_id',
        'scenario_name',
        'recommended_price',
        'win_probability',
        'risk_level',
        'is_recommended',
        'metadata',
        'rationale',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_recommended' => 'boolean',
            'recommended_price' => 'decimal:6',
            'win_probability' => 'decimal:2',
        ];
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class);
    }
}
