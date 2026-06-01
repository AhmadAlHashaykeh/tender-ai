<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StandardizationSuggestion extends Model
{
    protected $fillable = [
        'import_row_id',
        'input_hash',
        'entity_type',
        'entity_id',
        'suggested_standardized_drug_id',
        'suggested_company_id',
        'confidence',
        'status',
        'source',
        'suggestion_data',
        'rationale',
    ];

    protected function casts(): array
    {
        return [
            'suggestion_data' => 'array',
            'confidence' => 'decimal:2',
        ];
    }

    public function importRow(): BelongsTo
    {
        return $this->belongsTo(ImportRow::class);
    }

    public function suggestedStandardizedDrug(): BelongsTo
    {
        return $this->belongsTo(StandardizedDrug::class, 'suggested_standardized_drug_id');
    }

    public function suggestedCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'suggested_company_id');
    }
}
