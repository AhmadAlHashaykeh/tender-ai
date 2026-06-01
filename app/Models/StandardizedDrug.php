<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StandardizedDrug extends Model
{
    protected $fillable = [
        'code',
        'inn',
        'display_name',
        'product_name_normalized',
        'dosage',
        'form',
        'strength',
        'strength_unit',
        'category',
        'is_active',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function drugAliases(): HasMany
    {
        return $this->hasMany(DrugAlias::class);
    }

    public function drugs(): HasMany
    {
        return $this->hasMany(Drug::class);
    }

    public function tenderItems(): HasMany
    {
        return $this->hasMany(TenderItem::class);
    }

    public function bidRecords(): HasMany
    {
        return $this->hasMany(BidRecord::class);
    }

    public function pricingStatistics(): HasMany
    {
        return $this->hasMany(PricingStatistic::class);
    }
}
