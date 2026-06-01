<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BidRecord extends Model
{
    protected $fillable = [
        'tender_item_id',
        'company_id',
        'standardized_drug_id',
        'tender_id',
        'country_id',
        'bid_status',
        'is_winner',
        'row_type',
        'price_usd',
        'original_awarded_price',
        'currency_id',
        'quantity',
        'tender_value',
        'award_year',
        'source_import_row_id',
        'import_batch_id',
        'is_analytics_ready',
        'excluded_from_stats',
        'exclusion_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_winner' => 'boolean',
            'is_analytics_ready' => 'boolean',
            'excluded_from_stats' => 'boolean',
            'metadata' => 'array',
            'price_usd' => 'decimal:6',
            'original_awarded_price' => 'decimal:6',
            'quantity' => 'decimal:4',
            'tender_value' => 'decimal:4',
            'award_year' => 'integer',
        ];
    }

    public function tenderItem(): BelongsTo
    {
        return $this->belongsTo(TenderItem::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function standardizedDrug(): BelongsTo
    {
        return $this->belongsTo(StandardizedDrug::class);
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function sourceImportRow(): BelongsTo
    {
        return $this->belongsTo(ImportRow::class, 'source_import_row_id');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function scopeAnalyticsEligible(Builder $query): Builder
    {
        return $query
            ->where('is_analytics_ready', true)
            ->where('excluded_from_stats', false)
            ->where('bid_status', 'awarded')
            ->where('is_winner', true)
            ->whereNotNull('price_usd')
            ->where('price_usd', '>', 0)
            ->whereNotNull('standardized_drug_id')
            ->whereNotNull('country_id');
    }
}
