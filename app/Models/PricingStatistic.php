<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingStatistic extends Model
{
    protected $fillable = [
        'standardized_drug_id',
        'country_id',
        'region_id',
        'currency_id',
        'award_count',
        'last_unit_price',
        'avg_unit_price',
        'weighted_avg_unit_price',
        'median_unit_price',
        'min_unit_price',
        'max_unit_price',
        'price_std_dev',
        'last_award_date',
        'trend_direction',
        'trend_pct',
        'top_winner_company_id',
        'distinct_winners_count',
        'stats_version',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'last_award_date' => 'date',
            'calculated_at' => 'datetime',
            'last_unit_price' => 'decimal:6',
            'avg_unit_price' => 'decimal:6',
            'weighted_avg_unit_price' => 'decimal:6',
            'median_unit_price' => 'decimal:6',
            'min_unit_price' => 'decimal:6',
            'max_unit_price' => 'decimal:6',
            'price_std_dev' => 'decimal:6',
            'trend_pct' => 'decimal:4',
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

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function topWinnerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'top_winner_company_id');
    }
}
