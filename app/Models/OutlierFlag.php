<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutlierFlag extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'flag_type',
        'severity',
        'reason',
        'deviation_score',
        'is_resolved',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_resolved' => 'boolean',
            'deviation_score' => 'decimal:4',
        ];
    }
}
