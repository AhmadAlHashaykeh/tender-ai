<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionCalculation extends Model
{
    protected $fillable = [
        'prediction_id',
        'last_winning_price',
        'average_price',
        'weighted_average_price',
        'median_price',
        'min_price',
        'max_price',
        'recommended_price',
        'price_trend',
        'trend_pct',
        'quantity_factor',
        'competition_level',
        'competition_score',
        'outlier_count',
        'historical_award_count',
        'confidence_score',
        'calculation_model_version',
        'calculation_details',
    ];

    protected function casts(): array
    {
        return [
            'calculation_details' => 'array',
            'last_winning_price' => 'decimal:6',
            'average_price' => 'decimal:6',
            'weighted_average_price' => 'decimal:6',
            'median_price' => 'decimal:6',
            'min_price' => 'decimal:6',
            'max_price' => 'decimal:6',
            'recommended_price' => 'decimal:6',
            'trend_pct' => 'decimal:4',
            'quantity_factor' => 'decimal:4',
            'competition_score' => 'decimal:4',
            'confidence_score' => 'decimal:2',
        ];
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class);
    }
}
