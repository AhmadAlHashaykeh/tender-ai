<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name',
        'normalized_name',
        'country_id',
        'is_active',
        'source',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function companyAliases(): HasMany
    {
        return $this->hasMany(CompanyAlias::class);
    }

    public function bidRecords(): HasMany
    {
        return $this->hasMany(BidRecord::class);
    }
}
