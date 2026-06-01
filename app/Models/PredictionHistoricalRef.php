<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionHistoricalRef extends Model
{
    protected $fillable = [
        'prediction_id',
        'bid_record_id',
        'tender_id',
        'standardized_drug_id',
        'reference_price_usd',
        'weight',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'reference_price_usd' => 'decimal:6',
            'weight' => 'decimal:4',
        ];
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(Prediction::class);
    }

    public function bidRecord(): BelongsTo
    {
        return $this->belongsTo(BidRecord::class);
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function standardizedDrug(): BelongsTo
    {
        return $this->belongsTo(StandardizedDrug::class);
    }
}
