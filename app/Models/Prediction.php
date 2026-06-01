<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prediction extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'tender_id',
        'standardized_drug_id',
        'quantity',
        'quantity_unit',
        'discount_percentage',
        'market_calculated_price',
        'final_recommended_price',
        'recommended_price',
        'currency_id',
        'win_probability',
        'risk_level',
        'status',
        'confidence_score',
        'source',
        'recommendation_mode',
        'context_hash',
        'backend_recommended_price',
        'openai_called',
        'calculation_model_version',
        'stats_version',
        'context_snapshot',
        'ai_model',
        'ai_prompt_hash',
        'ai_response_raw',
        'ai_narrative',
        'ai_narrative_generated_at',
        'ai_model_used',
        'ai_tokens_used',
        'ai_response_ms',
        'rationale',
        'processing_time_ms',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'context_snapshot' => 'array',
            'ai_response_raw' => 'array',
            'openai_called' => 'boolean',
            'ai_narrative_generated_at' => 'datetime',
            'completed_at' => 'datetime',
            'quantity' => 'decimal:4',
            'discount_percentage' => 'decimal:2',
            'market_calculated_price' => 'decimal:6',
            'final_recommended_price' => 'decimal:6',
            'recommended_price' => 'decimal:6',
            'backend_recommended_price' => 'decimal:6',
            'win_probability' => 'decimal:2',
            'confidence_score' => 'decimal:2',
            'ai_tokens_used' => 'integer',
            'ai_response_ms' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function standardizedDrug(): BelongsTo
    {
        return $this->belongsTo(StandardizedDrug::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function predictionCalculations(): HasMany
    {
        return $this->hasMany(PredictionCalculation::class);
    }

    public function predictionScenarios(): HasMany
    {
        return $this->hasMany(PredictionScenario::class);
    }

    public function contextSnapshots(): HasMany
    {
        return $this->hasMany(PredictionContextSnapshot::class);
    }

    public function historicalRefs(): HasMany
    {
        return $this->hasMany(PredictionHistoricalRef::class);
    }

    public function accuracyRecords(): HasMany
    {
        return $this->hasMany(PredictionAccuracyRecord::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function latestCalculation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PredictionCalculation::class)->latestOfMany();
    }
}
