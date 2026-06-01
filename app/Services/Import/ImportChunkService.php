<?php

namespace App\Services\Import;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportChunkStatus;
use App\Enums\ImportRowValidationStatus;
use App\Jobs\ProcessImportChunkJob;
use App\Services\Import\ImportJobDispatcher;
use App\Models\ImportBatch;
use App\Models\ImportChunk;
use App\Models\ImportRow;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportChunkService
{
    public function __construct(
        protected ImportParserService $parser,
        protected ImportBatchService $importBatchService,
        protected DuplicateDetectionService $duplicateDetector,
    ) {}

    /**
     * @return list<ImportChunk>
     */
    public function createChunksForBatch(ImportBatch $batch): array
    {
        $absolutePath = Storage::disk(config('import.disk', 'local'))->path($batch->file_path);

        if (! is_file($absolutePath)) {
            throw new \RuntimeException('Uploaded file could not be found on disk.');
        }

        $totalRows = $this->parser->countDataRows($absolutePath);

        if ($totalRows === 0) {
            return [];
        }

        $chunkSize = ImportChunkSizeResolver::forRowCount($totalRows, 'import.chunk_size', 500);
        $chunkCount = (int) ceil($totalRows / $chunkSize);

        $chunks = [];

        for ($i = 0; $i < $chunkCount; $i++) {
            $startRow = ($i * $chunkSize) + 1;
            $endRow = min(($i + 1) * $chunkSize, $totalRows);
            $rowsInChunk = $endRow - $startRow + 1;

            $chunks[] = ImportChunk::query()->create([
                'import_batch_id' => $batch->id,
                'chunk_number' => $i + 1,
                'start_row' => $startRow,
                'end_row' => $endRow,
                'status' => ImportChunkStatus::Pending->value,
                'total_rows' => $rowsInChunk,
            ]);
        }

        $batch->update([
            'row_count' => $totalRows,
            'metadata' => array_merge($batch->metadata ?? [], [
                'total_chunks' => count($chunks),
                'chunk_size' => $chunkSize,
                'data_row_count' => $totalRows,
            ]),
        ]);

        return $chunks;
    }

    public function dispatchChunkJobs(ImportBatch $batch): void
    {
        $chunks = ImportChunk::query()
            ->where('import_batch_id', $batch->id)
            ->where('status', ImportChunkStatus::Pending->value)
            ->orderBy('chunk_number')
            ->get();

        $dispatcher = app(ImportJobDispatcher::class);

        foreach ($chunks as $chunk) {
            $dispatcher->dispatch(new ProcessImportChunkJob($chunk), $batch);
        }
    }

    public function processChunk(ImportChunk $chunk): void
    {
        $batch = $chunk->importBatch()->first();

        if (! $batch) {
            return;
        }

        if ($chunk->status === ImportChunkStatus::Completed->value) {
            return;
        }

        $absolutePath = Storage::disk(config('import.disk', 'local'))->path($batch->file_path);

        if (! is_file($absolutePath)) {
            $this->failChunk($chunk, 'Uploaded file could not be found on disk.');

            return;
        }

        $chunk->update([
            'status' => ImportChunkStatus::Processing->value,
            'started_at' => $chunk->started_at ?? now(),
            'error_message' => null,
        ]);

        $userMapping = $batch->metadata['confirmed_mapping'] ?? null;

        try {
            $parsed = $this->parser->parseRowRange(
                $absolutePath,
                $chunk->start_row,
                $chunk->end_row,
                $userMapping,
            );
        } catch (Throwable $exception) {
            $this->failChunk($chunk, $exception->getMessage());
            $this->checkBatchFinalization($batch->fresh());

            return;
        }

        $valid = 0;
        $warnings = 0;
        $invalid = 0;
        $failed = 0;
        $processed = 0;

        foreach ($parsed['rows'] as $payload) {
            $processed++;

            try {
                $this->importBatchService->createImportRow($batch, $payload);
            } catch (Throwable $exception) {
                $failed++;

                continue;
            }
        }

        $fileRowNumbers = collect($parsed['rows'])->pluck('row_number')->map(fn ($n) => (int) $n)->all();

        $this->duplicateDetector->detectForChunk($batch->id, $fileRowNumbers);

        $chunkRowIds = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->whereIn('row_number', $fileRowNumbers)
            ->pluck('id');

        if ($chunkRowIds->isNotEmpty()) {
            $valid = ImportRow::query()
                ->whereIn('id', $chunkRowIds)
                ->where('validation_status', ImportRowValidationStatus::Valid->value)
                ->count();
            $warnings = ImportRow::query()
                ->whereIn('id', $chunkRowIds)
                ->where('validation_status', ImportRowValidationStatus::Warning->value)
                ->count();
            $invalid = ImportRow::query()
                ->whereIn('id', $chunkRowIds)
                ->where('validation_status', ImportRowValidationStatus::Invalid->value)
                ->count();
        }

        $duplicates = ImportRow::query()
            ->whereIn('id', $chunkRowIds)
            ->where('validation_status', ImportRowValidationStatus::Duplicate->value)
            ->count();

        $chunk->update([
            'status' => ImportChunkStatus::Completed->value,
            'processed_rows' => $processed,
            'valid_rows' => $valid,
            'warning_rows' => $warnings,
            'invalid_rows' => $invalid,
            'duplicate_rows' => $duplicates,
            'failed_rows' => $failed,
            'completed_at' => now(),
        ]);

        $this->syncBatchProgress($batch->fresh());
        $this->checkBatchFinalization($batch->fresh());
    }

    public function retryFailedChunks(ImportBatch $batch): int
    {
        $failedChunks = ImportChunk::query()
            ->where('import_batch_id', $batch->id)
            ->where('status', ImportChunkStatus::Failed->value)
            ->get();

        if ($failedChunks->isEmpty()) {
            return 0;
        }

        foreach ($failedChunks as $chunk) {
            $this->clearChunkRows($batch, $chunk);

            $chunk->update([
                'status' => ImportChunkStatus::Pending->value,
                'processed_rows' => 0,
                'valid_rows' => 0,
                'warning_rows' => 0,
                'invalid_rows' => 0,
                'duplicate_rows' => 0,
                'failed_rows' => 0,
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null,
            ]);

            ProcessImportChunkJob::dispatch($chunk);
        }

        $batch->update([
            'status' => ImportBatchStatus::Processing->value,
            'completed_at' => null,
        ]);

        return $failedChunks->count();
    }

    protected function clearChunkRows(ImportBatch $batch, ImportChunk $chunk): void
    {
        $userMapping = $batch->metadata['confirmed_mapping'] ?? null;
        $absolutePath = Storage::disk(config('import.disk', 'local'))->path($batch->file_path);

        if (! is_file($absolutePath)) {
            ImportRow::query()
                ->where('import_batch_id', $batch->id)
                ->whereBetween('row_number', [$chunk->start_row, $chunk->end_row + 1])
                ->delete();

            return;
        }

        try {
            $parsed = $this->parser->parseRowRange(
                $absolutePath,
                $chunk->start_row,
                $chunk->end_row,
                $userMapping,
            );
            $rowNumbers = collect($parsed['rows'])->pluck('row_number')->all();

            if ($rowNumbers !== []) {
                ImportRow::query()
                    ->where('import_batch_id', $batch->id)
                    ->whereIn('row_number', $rowNumbers)
                    ->delete();
            }
        } catch (Throwable) {
            ImportRow::query()
                ->where('import_batch_id', $batch->id)
                ->whereBetween('row_number', [$chunk->start_row, $chunk->end_row + 1000])
                ->delete();
        }
    }

    protected function failChunk(ImportChunk $chunk, string $message): void
    {
        $chunk->update([
            'status' => ImportChunkStatus::Failed->value,
            'error_message' => $message,
            'completed_at' => now(),
        ]);
    }

    public function checkBatchFinalization(ImportBatch $batch): void
    {
        if (! $batch->usesChunkedImport()) {
            return;
        }

        $pending = ImportChunk::query()
            ->where('import_batch_id', $batch->id)
            ->whereIn('status', [
                ImportChunkStatus::Pending->value,
                ImportChunkStatus::Processing->value,
            ])
            ->exists();

        if ($pending) {
            return;
        }

        $failedChunks = ImportChunk::query()
            ->where('import_batch_id', $batch->id)
            ->where('status', ImportChunkStatus::Failed->value)
            ->count();

        $completedChunks = ImportChunk::query()
            ->where('import_batch_id', $batch->id)
            ->where('status', ImportChunkStatus::Completed->value)
            ->count();

        if ($completedChunks === 0 && $failedChunks > 0) {
            $batch->update([
                'status' => ImportBatchStatus::Failed->value,
                'metadata' => array_merge($batch->metadata ?? [], [
                    'failure_reason' => 'All import chunks failed.',
                ]),
                'completed_at' => now(),
            ]);

            return;
        }

        $this->duplicateDetector->detectForBatch($batch->id);

        $duplicateCount = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->where('validation_status', ImportRowValidationStatus::Duplicate->value)
            ->count();

        $parsedStub = [
            'headers' => $batch->metadata['detected_headers'] ?? [],
            'mapped_headers' => $batch->metadata['mapped_headers'] ?? [],
            'rows' => [],
            'mapping_result' => [
                'overall_confidence' => $batch->metadata['mapping_confidence'] ?? 100,
                'mappings' => $batch->metadata['column_mappings'] ?? [],
                'extra_columns' => $batch->metadata['extra_columns'] ?? [],
            ],
        ];

        $this->importBatchService->finalizeBatchFromChunks(
            $batch->fresh(),
            $parsedStub,
            ['duplicate_count' => $duplicateCount, 'review_pending' => 0],
            $failedChunks > 0,
        );
    }

    public function syncBatchProgress(ImportBatch $batch): void
    {
        $chunks = ImportChunk::query()->where('import_batch_id', $batch->id)->get();

        $batch->update([
            'processed_count' => (int) $chunks->sum('processed_rows'),
            'success_count' => (int) $chunks->sum('valid_rows'),
            'error_count' => (int) $chunks->sum('invalid_rows'),
            'duplicate_count' => (int) $chunks->sum('duplicate_rows'),
            'metadata' => array_merge($batch->metadata ?? [], [
                'valid_rows' => (int) $chunks->sum('valid_rows'),
                'invalid_rows' => (int) $chunks->sum('invalid_rows'),
                'warning_rows' => (int) $chunks->sum('warning_rows'),
                'duplicate_rows' => (int) $chunks->sum('duplicate_rows'),
            ]),
        ]);
    }

}
