<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'uuid',
        'filename',
        'original_filename',
        'file_path',
        'file_hash',
        'uploaded_by',
        'row_count',
        'processed_count',
        'success_count',
        'error_count',
        'duplicate_count',
        'status',
        'source_type',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function importRows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    public function importChunks(): HasMany
    {
        return $this->hasMany(ImportChunk::class);
    }

    public function standardizationChunks(): HasMany
    {
        return $this->hasMany(StandardizationChunk::class);
    }

    public function materializationChunks(): HasMany
    {
        return $this->hasMany(MaterializationChunk::class);
    }

    public function usesChunkedImport(): bool
    {
        return $this->importChunks()->exists();
    }

    public function usesChunkedStandardization(): bool
    {
        return $this->standardizationChunks()->exists();
    }

    public function usesChunkedMaterialization(): bool
    {
        return $this->materializationChunks()->exists();
    }

    public function isMaterializationRunning(): bool
    {
        return in_array($this->metadata['materialization_status'] ?? 'not_started', [
            'preparing',
            'processing',
        ], true);
    }
}
