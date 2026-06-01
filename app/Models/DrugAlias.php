<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrugAlias extends Model
{
    protected $fillable = [
        'standardized_drug_id',
        'alias_value',
        'normalized_alias',
        'alias_type',
        'source',
        'confidence',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:2',
            'usage_count' => 'integer',
        ];
    }

    public function standardizedDrug(): BelongsTo
    {
        return $this->belongsTo(StandardizedDrug::class);
    }
}
