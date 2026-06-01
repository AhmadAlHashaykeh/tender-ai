<?php

namespace App\Services\Import;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportRowValidationStatus;
use App\Jobs\ProcessImportBatchJob;
use App\Models\ImportChunk;
use App\Models\ImportBatch;
use App\Models\ImportMappingTemplate;
use App\Models\ImportRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ImportBatchService
{
    public function __construct(
        protected ImportParserService $parser,
        protected ImportValidatorService $validator,
        protected DuplicateDetectionService $duplicateDetector,
        protected ColumnMappingService $columnMapper,
        protected ImportQualityScoreService $qualityScoreService,
    ) {}

    public function storeUpload(UploadedFile $file, User $user): ImportBatch
    {
        $uuid = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = $uuid.'.'.$extension;
        $directory = trim(config('import.storage_path', 'imports'), '/');
        $relativePath = $directory.'/'.$storedName;

        $file->storeAs($directory, $storedName, config('import.disk', 'local'));

        $batch = ImportBatch::query()->create([
            'uuid' => $uuid,
            'filename' => $storedName,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $relativePath,
            'file_hash' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $user->id,
            'status' => ImportBatchStatus::Uploaded->value,
            'source_type' => $extension,
            'metadata' => [
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ],
        ]);

        $this->prepareMappingPreview($batch);

        return $batch->fresh();
    }

    /**
     * Parse headers and store mapping preview metadata — does not import rows yet.
     */
    public function prepareMappingPreview(ImportBatch $batch): void
    {
        $absolutePath = Storage::disk(config('import.disk', 'local'))->path($batch->file_path);

        if (! is_file($absolutePath)) {
            $this->markFailed($batch, 'Uploaded file could not be found on disk.');

            return;
        }

        try {
            $preview = $this->parser->parseHeaders($absolutePath);
        } catch (Throwable $exception) {
            $this->markFailed($batch, $exception->getMessage());

            return;
        }

        $mappingResult = $preview['mapping_result'];

        $batch->update([
            'status' => ImportBatchStatus::AwaitingMapping->value,
            'metadata' => array_merge($batch->metadata ?? [], [
                'detected_headers' => $preview['headers'],
                'mapped_headers' => $preview['mapped_headers'],
                'column_mappings' => $mappingResult['mappings'] ?? [],
                'missing_required' => $mappingResult['missing_required'] ?? [],
                'missing_drug_identity' => $mappingResult['missing_drug_identity'] ?? false,
                'extra_columns' => $mappingResult['extra_columns'] ?? [],
                'mapping_confidence' => $mappingResult['overall_confidence'] ?? 0,
                'sample_rows' => array_slice($preview['sample_rows'], 0, 3),
                'estimated_row_count' => $preview['total_row_count'],
            ]),
        ]);
    }

    /**
     * Persist confirmed column mapping (no row processing).
     *
     * @param  array<string, int|null>  $userMapping  canonical field => column index
     */
    public function confirmMapping(
        ImportBatch $batch,
        array $userMapping,
        ?string $templateName = null,
        ?User $user = null,
    ): ImportBatch {
        if ($templateName !== null && $user !== null) {
            $this->saveMappingTemplate($templateName, $userMapping, $user);
        }

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'confirmed_mapping' => $userMapping,
                'mapping_confirmed_at' => now()->toIso8601String(),
            ]),
            'status' => ImportBatchStatus::Queued->value,
        ]);

        return $batch->fresh();
    }

    /**
     * Queue background import processing after mapping is confirmed.
     */
    public function dispatchImportProcessing(ImportBatch $batch): void
    {
        app(ImportPipelineOrchestratorService::class)->markProcessingMode($batch);

        app(ImportJobDispatcher::class)->dispatch(new ProcessImportBatchJob($batch), $batch);
    }

    /**
     * @param  array<string, int|null>  $userMapping
     */
    public function confirmMappingAndProcess(
        ImportBatch $batch,
        array $userMapping,
        ?string $templateName = null,
        ?User $user = null,
    ): void {
        $batch = $this->confirmMapping($batch, $userMapping, $templateName, $user);
        $this->dispatchImportProcessing($batch);
    }

    /**
     * Orchestrate chunked import: create chunks and queue per-chunk jobs.
     */
    public function dispatchChunkedImport(ImportBatch $batch): void
    {
        $absolutePath = Storage::disk(config('import.disk', 'local'))->path($batch->file_path);

        if (! is_file($absolutePath)) {
            $this->markFailed($batch, 'Uploaded file could not be found on disk.');

            return;
        }

        $userMapping = $batch->metadata['confirmed_mapping'] ?? null;

        try {
            $preview = $this->parser->parseHeaders($absolutePath, $userMapping);
        } catch (Throwable $exception) {
            $this->markFailed($batch, $exception->getMessage());

            return;
        }

        $missingHeaders = $this->parser->missingRequiredHeaders($preview['mapped_headers']);

        if ($missingHeaders !== []) {
            $this->markHeaderValidationFailed($batch, $missingHeaders, $preview);

            return;
        }

        $batch->update([
            'status' => ImportBatchStatus::Processing->value,
            'started_at' => $batch->started_at ?? now(),
            'metadata' => array_merge($batch->metadata ?? [], [
                'mapped_headers' => $preview['mapped_headers'],
                'detected_headers' => $preview['headers'],
                'column_mappings' => $preview['mapping_result']['mappings'] ?? [],
                'extra_columns' => $preview['mapping_result']['extra_columns'] ?? [],
            ]),
        ]);

        $chunkService = app(ImportChunkService::class);
        $chunks = $chunkService->createChunksForBatch($batch->fresh());

        if ($chunks === []) {
            $this->finalizeBatchFromChunks($batch->fresh(), [
                'headers' => $preview['headers'],
                'mapped_headers' => $preview['mapped_headers'],
                'rows' => [],
                'mapping_result' => $preview['mapping_result'],
            ], ['duplicate_count' => 0, 'review_pending' => 0]);

            return;
        }

        $chunkService->dispatchChunkJobs($batch->fresh());
    }

    /**
     * @param  array<string, int|null>  $userMapping
     */
    public function saveMappingTemplate(string $name, array $userMapping, User $user): ImportMappingTemplate
    {
        return ImportMappingTemplate::query()->updateOrCreate(
            ['name' => $name, 'created_by' => $user->id],
            [
                'mapping' => $userMapping,
                'metadata' => ['saved_at' => now()->toIso8601String()],
            ]
        );
    }

    public function process(ImportBatch $batch): void
    {
        $absolutePath = Storage::disk(config('import.disk', 'local'))->path($batch->file_path);

        if (! is_file($absolutePath)) {
            $this->markFailed($batch, 'Uploaded file could not be found on disk.');

            return;
        }

        $batch->update([
            'status' => ImportBatchStatus::Parsing->value,
            'started_at' => $batch->started_at ?? now(),
        ]);

        $userMapping = $batch->metadata['confirmed_mapping'] ?? null;

        try {
            $parsed = $this->parser->parse($absolutePath, $userMapping);
        } catch (Throwable $exception) {
            $this->markFailed($batch, $exception->getMessage());

            return;
        }

        $missingHeaders = $this->parser->missingRequiredHeaders($parsed['mapped_headers']);

        if ($missingHeaders !== []) {
            $this->markHeaderValidationFailed($batch, $missingHeaders, $parsed);

            return;
        }

        $batch->update(['status' => ImportBatchStatus::Validating->value]);

        DB::transaction(function () use ($batch, $parsed) {
            ImportRow::query()->where('import_batch_id', $batch->id)->delete();

            foreach ($parsed['rows'] as $payload) {
                $this->createImportRow($batch, $payload);
            }
        });

        $duplicateStats = $this->duplicateDetector->detectForBatch($batch->id);

        $this->finalizeBatch($batch, $parsed, $duplicateStats);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createImportRow(ImportBatch $batch, array $payload): ImportRow
    {
        $canonical = $this->columnMapper->normalizeCanonicalKeys($payload['canonical'] ?? []);
        $validation = $this->validator->validate($canonical);
        $normalized = $validation['normalized_data'];

        $rowHash = $this->duplicateDetector->generateRowHash($normalized);

        return ImportRow::query()->create([
            'import_batch_id' => $batch->id,
            'row_number' => $payload['row_number'],
            'row_hash' => $rowHash,
            'raw_code' => $canonical['code'] ?? null,
            'raw_inn' => $canonical['inn'] ?? null,
            'raw_product_name' => $canonical['product_name'] ?? null,
            'raw_country' => $canonical['country'] ?? null,
            'raw_tender_number' => $canonical['tender_number'] ?? null,
            'raw_awarded_price' => $canonical['awarded_price'] ?? null,
            'raw_price_usd' => $canonical['price_usd'] ?? null,
            'raw_winner' => $canonical['winner'] ?? null,
            'raw_company_name' => $canonical['company_name'] ?? null,
            'raw_version' => $canonical['version'] ?? null,
            'raw_year' => $canonical['year'] ?? null,
            'raw_qty' => $canonical['quantity'] ?? $canonical['qty'] ?? null,
            'raw_tender_value' => $canonical['tender_value'] ?? null,
            'raw_data' => [
                'by_header' => $payload['raw_by_header'] ?? [],
                'canonical' => $canonical,
                'additional_columns' => $payload['additional_columns'] ?? [],
                'extra_columns' => $payload['extra_columns'] ?? [],
            ],
            'normalized_data' => $normalized,
            'validation_status' => $validation['validation_status'],
            'standardization_status' => 'pending',
            'row_type' => 'winning_bid',
            'confidence_score' => $validation['confidence_score'],
            'error_message' => $validation['error_message'],
            'warning_messages' => $validation['warning_messages'],
        ]);
    }

    /**
     * @param  array{headers: array, mapped_headers: array, rows: array, mapping_result?: array}  $parsed
     * @param  array{duplicate_count: int, review_pending: int}  $duplicateStats
     */
    protected function finalizeBatch(ImportBatch $batch, array $parsed, array $duplicateStats): void
    {
        $rows = ImportRow::query()->where('import_batch_id', $batch->id);

        $total = (clone $rows)->count();
        $invalid = (clone $rows)->where('validation_status', ImportRowValidationStatus::Invalid->value)->count();
        $valid = (clone $rows)->where('validation_status', ImportRowValidationStatus::Valid->value)->count();
        $warnings = (clone $rows)->where('validation_status', ImportRowValidationStatus::Warning->value)->count();
        $duplicates = (clone $rows)->where('validation_status', ImportRowValidationStatus::Duplicate->value)->count();

        $status = $invalid > 0
            ? ImportBatchStatus::CompletedWithErrors->value
            : ImportBatchStatus::Completed->value;

        $validationReview = (clone $rows)->whereIn('validation_status', [
            ImportRowValidationStatus::Warning->value,
            ImportRowValidationStatus::Duplicate->value,
        ])->count();

        $mappingResult = $parsed['mapping_result'] ?? [];
        $mappingConfidence = $mappingResult['overall_confidence']
            ?? ($batch->metadata['mapping_confidence'] ?? 100);

        $metadata = array_merge($batch->metadata ?? [], [
            'mapped_headers' => $parsed['mapped_headers'],
            'detected_headers' => $parsed['headers'],
            'column_mappings' => $mappingResult['mappings'] ?? ($batch->metadata['column_mappings'] ?? []),
            'extra_columns' => $mappingResult['extra_columns'] ?? ($batch->metadata['extra_columns'] ?? []),
            'mapping_confidence' => $mappingConfidence,
            'valid_rows' => $valid,
            'invalid_rows' => $invalid,
            'warning_rows' => $warnings,
            'duplicate_rows' => $duplicates,
            'validation_review_rows' => $validationReview,
        ]);

        $batch->update([
            'row_count' => $total,
            'processed_count' => $total,
            'success_count' => $valid,
            'error_count' => $invalid,
            'duplicate_count' => $duplicateStats['duplicate_count'],
            'status' => $status,
            'metadata' => $metadata,
            'completed_at' => now(),
        ]);

        $this->refreshQualityScore($batch->fresh());

        app(ImportPipelineOrchestratorService::class)->onImportValidationComplete($batch->fresh());
    }

    /**
     * @param  array{headers: array, mapped_headers: array, rows: array, mapping_result?: array}  $parsed
     * @param  array{duplicate_count: int, review_pending: int}  $duplicateStats
     */
    public function finalizeBatchFromChunks(
        ImportBatch $batch,
        array $parsed,
        array $duplicateStats,
        bool $hasFailedChunks = false,
    ): void {
        $rows = ImportRow::query()->where('import_batch_id', $batch->id);

        $total = (clone $rows)->count();
        $invalid = (clone $rows)->where('validation_status', ImportRowValidationStatus::Invalid->value)->count();
        $valid = (clone $rows)->where('validation_status', ImportRowValidationStatus::Valid->value)->count();
        $warnings = (clone $rows)->where('validation_status', ImportRowValidationStatus::Warning->value)->count();
        $duplicates = (clone $rows)->where('validation_status', ImportRowValidationStatus::Duplicate->value)->count();

        $status = match (true) {
            $hasFailedChunks => ImportBatchStatus::CompletedWithErrors->value,
            $invalid > 0 => ImportBatchStatus::CompletedWithErrors->value,
            default => ImportBatchStatus::Completed->value,
        };

        $validationReview = (clone $rows)->whereIn('validation_status', [
            ImportRowValidationStatus::Warning->value,
            ImportRowValidationStatus::Duplicate->value,
        ])->count();

        $mappingResult = $parsed['mapping_result'] ?? [];
        $mappingConfidence = $mappingResult['overall_confidence']
            ?? ($batch->metadata['mapping_confidence'] ?? 100);

        $chunks = ImportChunk::query()->where('import_batch_id', $batch->id)->get();

        $metadata = array_merge($batch->metadata ?? [], [
            'mapped_headers' => $parsed['mapped_headers'],
            'detected_headers' => $parsed['headers'],
            'column_mappings' => $mappingResult['mappings'] ?? ($batch->metadata['column_mappings'] ?? []),
            'extra_columns' => $mappingResult['extra_columns'] ?? ($batch->metadata['extra_columns'] ?? []),
            'mapping_confidence' => $mappingConfidence,
            'valid_rows' => $valid,
            'invalid_rows' => $invalid,
            'warning_rows' => $warnings,
            'duplicate_rows' => $duplicates,
            'validation_review_rows' => $validationReview,
            'failed_chunks' => $chunks->where('status', 'failed')->count(),
        ]);

        $batch->update([
            'row_count' => $total,
            'processed_count' => $total,
            'success_count' => $valid,
            'error_count' => $invalid,
            'duplicate_count' => $duplicates > 0 ? $duplicates : $duplicateStats['duplicate_count'],
            'status' => $status,
            'metadata' => $metadata,
            'completed_at' => now(),
        ]);

        $this->refreshQualityScore($batch->fresh());

        app(ImportPipelineOrchestratorService::class)->onImportValidationComplete($batch->fresh());
    }

    /**
     * @return array{score: float, rating: string, breakdown: array<string, float>, factors: array<string, mixed>}
     */
    public function refreshQualityScore(ImportBatch $batch): array
    {
        $quality = $this->qualityScoreService->calculateForBatch($batch->fresh());

        $batch->update([
            'metadata' => array_merge($batch->metadata ?? [], [
                'import_quality_score' => $quality['score'],
                'import_quality_rating' => $quality['rating'],
                'import_quality_breakdown' => $quality['breakdown'],
            ]),
        ]);

        return $quality;
    }

    /**
     * @param  array<string, mixed>  $canonical
     */
    public function storeManualEntry(User $user, array $canonical): ImportBatch
    {
        $stringCanonical = $this->stringifyCanonical($canonical);

        $batch = ImportBatch::query()->create([
            'uuid' => (string) Str::uuid(),
            'filename' => 'manual-entry-'.now()->format('Ymd-His').'.json',
            'original_filename' => 'Manual historical entry',
            'file_path' => null,
            'file_hash' => null,
            'uploaded_by' => $user->id,
            'status' => ImportBatchStatus::Validating->value,
            'source_type' => 'manual',
            'metadata' => [
                'entry_mode' => 'manual_historical',
                'submitted_at' => now()->toIso8601String(),
                'mapping_confidence' => 100,
            ],
            'started_at' => now(),
        ]);

        $payload = [
            'row_number' => 1,
            'canonical' => $stringCanonical,
            'raw_by_header' => [],
            'additional_columns' => [],
        ];

        DB::transaction(function () use ($batch, $payload): void {
            $this->createImportRow($batch, $payload);
        });

        $duplicateStats = $this->duplicateDetector->detectForBatch($batch->id);
        $this->finalizeBatch($batch, [
            'headers' => [],
            'mapped_headers' => [],
            'rows' => [$payload],
            'mapping_result' => ['overall_confidence' => 100, 'mappings' => [], 'extra_columns' => []],
        ], $duplicateStats);

        return $batch->fresh();
    }

    /**
     * @param  array{headers: array, mapped_headers: array}  $parsed
     * @param  list<string>  $missingHeaders
     */
    protected function markHeaderValidationFailed(ImportBatch $batch, array $missingHeaders, array $parsed): void
    {
        $message = 'Missing required columns: '.implode(', ', $missingHeaders).'.';

        $batch->update([
            'status' => ImportBatchStatus::Failed->value,
            'row_count' => 0,
            'processed_count' => 0,
            'success_count' => 0,
            'error_count' => 0,
            'metadata' => array_merge($batch->metadata ?? [], [
                'failure_reason' => $message,
                'missing_headers' => $missingHeaders,
                'detected_headers' => $parsed['headers'],
                'mapped_headers' => $parsed['mapped_headers'],
                'expected_headers' => $this->parser->requiredHeaderLabels(),
            ]),
            'completed_at' => now(),
        ]);
    }

    protected function markFailed(ImportBatch $batch, string $message): void
    {
        $batch->update([
            'status' => ImportBatchStatus::Failed->value,
            'metadata' => array_merge($batch->metadata ?? [], ['failure_reason' => $message]),
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $canonical
     * @return array<string, ?string>
     */
    protected function stringifyCanonical(array $canonical): array
    {
        $fields = config('import.canonical_fields', array_keys(config('import.expected_columns', [])));
        $result = [];

        foreach ($fields as $field) {
            $value = $canonical[$field] ?? $canonical[$field === 'quantity' ? 'qty' : $field] ?? null;
            $result[$field] = $value === null || $value === '' ? null : (string) $value;
        }

        return $this->columnMapper->normalizeCanonicalKeys($result);
    }

    public function destroy(ImportBatch $batch): void
    {
        if ($batch->file_path) {
            Storage::disk(config('import.disk', 'local'))->delete($batch->file_path);
        }

        $batch->delete();
    }

    /**
     * @return array{
     *     valid: int,
     *     invalid: int,
     *     warning: int,
     *     duplicate: int,
     *     total: int,
     *     validation_review: int,
     *     standardization_review: int
     * }
     */
    public function batchStats(ImportBatch $batch): array
    {
        $metadata = $batch->metadata ?? [];

        return [
            'total' => $batch->row_count,
            'valid' => $metadata['valid_rows'] ?? $batch->success_count,
            'invalid' => $metadata['invalid_rows'] ?? $batch->error_count,
            'warning' => $metadata['warning_rows'] ?? 0,
            'duplicate' => $metadata['duplicate_rows'] ?? $batch->duplicate_count,
            'validation_review' => $metadata['validation_review_rows']
                ?? $metadata['review_pending_rows']
                ?? 0,
            'standardization_review' => $metadata['standardization_review_rows'] ?? 0,
        ];
    }
}
