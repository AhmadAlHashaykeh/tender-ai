<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tender extends Model
{
    protected $fillable = [
        'tender_number',
        'country_id',
        'year',
        'version',
        'title',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'year' => 'integer',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function tenderItems(): HasMany
    {
        return $this->hasMany(TenderItem::class);
    }

    public function bidRecords(): HasMany
    {
        return $this->hasMany(BidRecord::class);
    }
}
