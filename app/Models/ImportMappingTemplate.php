<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportMappingTemplate extends Model
{
    protected $fillable = [
        'name',
        'created_by',
        'mapping',
        'column_aliases',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'column_aliases' => 'array',
            'metadata' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
