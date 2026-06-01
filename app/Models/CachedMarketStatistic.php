<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CachedMarketStatistic extends Model
{
    protected $fillable = [
        'cache_key',
        'standardized_drug_id',
        'country_id',
        'region_id',
        'scope',
        'statistics_payload',
        'stats_version',
        'calculated_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'statistics_payload' => 'array',
            'calculated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function standardizedDrug(): BelongsTo
    {
        return $this->belongsTo(StandardizedDrug::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
