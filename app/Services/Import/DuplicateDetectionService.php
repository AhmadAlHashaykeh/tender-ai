<?php

namespace App\Services\Import;

use App\Enums\ImportRowValidationStatus;
use App\Models\ImportRow;
use App\Models\ImportRowDuplicate;

class DuplicateDetectionService
{
    /**
     * @param  array<string, mixed>  $normalized
     */
    public function generateRowHash(array $normalized): string
    {
        $parts = [
            $normalized['tender_number'] ?? '',
            $normalized['country'] ?? '',
            (string) ($normalized['year'] ?? ''),
            $normalized['code'] ?? '',
            $normalized['inn'] ?? '',
            $normalized['product_name'] ?? '',
            $normalized['company_name'] ?? $normalized['winner'] ?? '',
            $this->formatNumber($normalized['price_usd'] ?? null),
            $this->formatNumber($normalized['qty'] ?? null),
        ];

        return hash('sha256', implode('|', $parts));
    }

    /**
     * Incremental duplicate detection for rows added by a chunk (intra-batch + cross-batch).
     *
     * @param  list<int>  $fileRowNumbers  Spreadsheet row_number values from the chunk parse
     * @return array{duplicate_count: int, review_pending: int}
     */
    public function detectForChunk(int $importBatchId, array $fileRowNumbers): array
    {
        if ($fileRowNumbers === []) {
            return ['duplicate_count' => 0, 'review_pending' => 0];
        }

        $newRows = ImportRow::query()
            ->where('import_batch_id', $importBatchId)
            ->whereIn('row_number', $fileRowNumbers)
            ->orderBy('row_number')
            ->get();

        if ($newRows->isEmpty()) {
            return ['duplicate_count' => 0, 'review_pending' => 0];
        }

        $priorHashes = ImportRow::query()
            ->where('import_batch_id', $importBatchId)
            ->whereNotIn('row_number', $fileRowNumbers)
            ->orderBy('row_number')
            ->get()
            ->keyBy('row_hash');

        $hashes = $newRows->pluck('row_hash')->filter()->unique()->values()->all();

        $existingHashes = ImportRow::query()
            ->whereIn('row_hash', $hashes)
            ->where('import_batch_id', '!=', $importBatchId)
            ->pluck('id', 'row_hash');

        $duplicateCount = 0;
        $reviewPending = 0;

        foreach ($newRows as $row) {
            $hash = $row->row_hash;

            if ($priorHashes->has($hash)) {
                $original = $priorHashes->get($hash);
                if ($original instanceof ImportRow) {
                    $this->markDuplicate($row, $original, 'intra_batch');
                    $duplicateCount++;
                }

                continue;
            }

            if ($existingHashes->has($hash)) {
                $original = ImportRow::query()->find($existingHashes->get($hash));
                if ($original !== null) {
                    $this->markDuplicate($row, $original, 'cross_batch');
                    $duplicateCount++;

                    continue;
                }
            }

            $priorHashes->put($hash, $row);

            if (in_array($row->validation_status, [
                ImportRowValidationStatus::Warning->value,
                ImportRowValidationStatus::Duplicate->value,
            ], true)) {
                $reviewPending++;
            }
        }

        return [
            'duplicate_count' => $duplicateCount,
            'review_pending' => $reviewPending,
        ];
    }

    /**
     * @return array{duplicate_count: int, review_pending: int}
     */
    public function detectForBatch(int $importBatchId): array
    {
        $rows = ImportRow::query()
            ->where('import_batch_id', $importBatchId)
            ->orderBy('row_number')
            ->get();

        $hashes = $rows->pluck('row_hash')->filter()->unique()->values()->all();

        $existingHashes = ImportRow::query()
            ->whereIn('row_hash', $hashes)
            ->where('import_batch_id', '!=', $importBatchId)
            ->pluck('id', 'row_hash');

        $seenInBatch = [];
        $duplicateCount = 0;
        $reviewPending = 0;

        foreach ($rows as $row) {
            $hash = $row->row_hash;

            if (isset($seenInBatch[$hash])) {
                $this->markDuplicate($row, $seenInBatch[$hash], 'intra_batch');
                $duplicateCount++;

                continue;
            }

            if ($existingHashes->has($hash)) {
                $original = ImportRow::query()->find($existingHashes->get($hash));
                if ($original !== null) {
                    $this->markDuplicate($row, $original, 'cross_batch');
                    $duplicateCount++;

                    continue;
                }
            }

            $seenInBatch[$hash] = $row;

            if (in_array($row->validation_status, [
                ImportRowValidationStatus::Warning->value,
                ImportRowValidationStatus::Duplicate->value,
            ], true)) {
                $reviewPending++;
            }
        }

        return [
            'duplicate_count' => $duplicateCount,
            'review_pending' => $reviewPending,
        ];
    }

    protected function markDuplicate(ImportRow $row, ImportRow $original, string $matchType): void
    {
        $warnings = $row->warning_messages ?? [];
        $warnings[] = 'Duplicate row detected ('.$matchType.').';

        $validationStatus = $row->validation_status === ImportRowValidationStatus::Invalid->value
            ? ImportRowValidationStatus::Invalid->value
            : ImportRowValidationStatus::Duplicate->value;

        $row->update([
            'validation_status' => $validationStatus,
            'warning_messages' => $warnings,
            'confidence_score' => min((float) ($row->confidence_score ?? 100), 50),
        ]);

        ImportRowDuplicate::query()->firstOrCreate(
            [
                'import_row_id' => $row->id,
                'duplicate_import_row_id' => $original->id,
            ],
            [
                'match_type' => $matchType,
                'confidence' => 100,
                'resolution_status' => 'pending',
            ]
        );
    }

    protected function formatNumber(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, 6, '.', '');
    }
}
