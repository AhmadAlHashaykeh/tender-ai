<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenderItem extends Model
{
    protected $fillable = [
        'tender_id',
        'source_import_row_id',
        'standardized_drug_id',
        'line_number',
        'quantity',
        'quantity_unit',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'quantity' => 'decimal:4',
        ];
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function standardizedDrug(): BelongsTo
    {
        return $this->belongsTo(StandardizedDrug::class);
    }

    public function bidRecords(): HasMany
    {
        return $this->hasMany(BidRecord::class);
    }
}
