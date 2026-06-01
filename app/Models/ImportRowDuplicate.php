<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRowDuplicate extends Model
{
    protected $fillable = [
        'import_row_id',
        'duplicate_import_row_id',
        'match_type',
        'confidence',
        'resolution_status',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:2',
        ];
    }

    public function importRow(): BelongsTo
    {
        return $this->belongsTo(ImportRow::class);
    }

    public function duplicateImportRow(): BelongsTo
    {
        return $this->belongsTo(ImportRow::class, 'duplicate_import_row_id');
    }
}
