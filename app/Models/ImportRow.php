<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportRow extends Model
{
    protected $fillable = [
        'import_batch_id',
        'row_number',
        'row_hash',
        'raw_code',
        'raw_inn',
        'raw_product_name',
        'raw_country',
        'raw_tender_number',
        'raw_awarded_price',
        'raw_price_usd',
        'raw_winner',
        'raw_company_name',
        'raw_version',
        'raw_year',
        'raw_qty',
        'raw_tender_value',
        'raw_data',
        'normalized_data',
        'validation_status',
        'standardization_status',
        'row_type',
        'confidence_score',
        'drug_confidence',
        'company_confidence',
        'tender_confidence',
        'error_message',
        'warning_messages',
        'standardized_drug_id',
        'company_id',
        'tender_id',
        'tender_item_id',
        'bid_record_id',
        'ai_assisted',
        'standardization_suggestion_id',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'normalized_data' => 'array',
            'warning_messages' => 'array',
            'ai_assisted' => 'boolean',
            'confidence_score' => 'decimal:2',
            'drug_confidence' => 'decimal:2',
            'company_confidence' => 'decimal:2',
            'tender_confidence' => 'decimal:2',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function standardizedDrug(): BelongsTo
    {
        return $this->belongsTo(StandardizedDrug::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function tenderItem(): BelongsTo
    {
        return $this->belongsTo(TenderItem::class);
    }

    public function bidRecord(): BelongsTo
    {
        return $this->belongsTo(BidRecord::class);
    }

    public function standardizationSuggestion(): BelongsTo
    {
        return $this->belongsTo(StandardizationSuggestion::class);
    }

    public function duplicateLinks(): HasMany
    {
        return $this->hasMany(ImportRowDuplicate::class);
    }
}
