<?php

namespace App\Services\Import;

use App\Enums\ImportRowValidationStatus;
use App\Enums\StandardizationStatus;
use App\Models\ImportBatch;
use App\Models\ImportRow;

class ImportQualityScoreService
{
    /**
     * @return array{
     *     score: float,
     *     rating: string,
     *     breakdown: array<string, float>,
     *     factors: array<string, mixed>
     * }
     */
    public function calculateForBatch(ImportBatch $batch): array
    {
        $rows = ImportRow::query()->where('import_batch_id', $batch->id);
        $total = (clone $rows)->count();

        if ($total === 0) {
            return $this->emptyResult();
        }

        $valid = (clone $rows)->where('validation_status', ImportRowValidationStatus::Valid->value)->count();
        $invalid = (clone $rows)->where('validation_status', ImportRowValidationStatus::Invalid->value)->count();
        $warnings = (clone $rows)->where('validation_status', ImportRowValidationStatus::Warning->value)->count();
        $duplicates = (clone $rows)->where('validation_status', ImportRowValidationStatus::Duplicate->value)->count();

        $acceptable = $valid + $warnings;
        $processable = max(1, $total - $invalid);

        $validationSuccess = $this->clampPercent(($acceptable / $total) * 100);
        $warningScore = $this->clampPercent(100 - (($warnings / $total) * 100));
        $missingDataScore = $this->clampPercent(100 - (($invalid / $total) * 100));

        $mappingConfidence = $this->clampPercent((float) ($batch->metadata['mapping_confidence'] ?? 100));

        $standardized = (clone $rows)->whereIn('standardization_status', [
            StandardizationStatus::AutoApproved->value,
            StandardizationStatus::Approved->value,
        ])->count();
        $standardizationConfidence = $this->clampPercent(($standardized / $processable) * 100);

        $avgRowConfidence = $this->clampPercent((float) ((clone $rows)->avg('confidence_score') ?? 0));

        $duplicateRate = ($duplicates / $total) * 100;
        $duplicateScore = $this->clampPercent(100 - $duplicateRate);

        $score = $this->clampPercent(
            ($validationSuccess * 0.20)
            + ($warningScore * 0.10)
            + ($mappingConfidence * 0.15)
            + ($standardizationConfidence * 0.20)
            + ($avgRowConfidence * 0.15)
            + ($duplicateScore * 0.10)
            + ($missingDataScore * 0.10)
        );

        return [
            'score' => $score,
            'rating' => $this->ratingForScore($score, $standardized, $processable, $invalid, $warnings),
            'breakdown' => [
                'validation_success' => $validationSuccess,
                'warning_score' => $warningScore,
                'mapping_confidence' => $mappingConfidence,
                'standardization_confidence' => $standardizationConfidence,
                'row_confidence_avg' => round($avgRowConfidence, 2),
                'duplicate_score' => $duplicateScore,
                'missing_data_score' => $missingDataScore,
            ],
            'factors' => [
                'total_rows' => $total,
                'valid_rows' => $valid,
                'invalid_rows' => $invalid,
                'warning_rows' => $warnings,
                'duplicate_rows' => $duplicates,
                'standardized_rows' => $standardized,
                'processable_rows' => $processable,
                'duplicate_rate' => round($duplicateRate, 2),
                'missing_data_rate' => round(($invalid / $total) * 100, 2),
                'warning_rate' => round(($warnings / $total) * 100, 2),
            ],
        ];
    }

    /**
     * @return array{score: float, rating: string, breakdown: array<string, float>, factors: array<string, mixed>}
     */
    protected function emptyResult(): array
    {
        return [
            'score' => 0.0,
            'rating' => 'Poor',
            'breakdown' => [],
            'factors' => ['total_rows' => 0],
        ];
    }

    protected function clampPercent(float $value): float
    {
        return round(max(0, min(100, $value)), 2);
    }

    public function ratingForScore(
        float $score,
        int $standardizedRows = 0,
        int $processableRows = 1,
        int $invalidRows = 0,
        int $warningRows = 0,
    ): string {
        $thresholds = config('import.quality_ratings', []);

        $rating = 'Poor';

        if ($score >= ($thresholds['excellent'] ?? 90)) {
            $rating = 'Excellent';
        } elseif ($score >= ($thresholds['good'] ?? 75)) {
            $rating = 'Good';
        } elseif ($score >= ($thresholds['needs_review'] ?? 50)) {
            $rating = 'Needs Review';
        }

        if ($standardizedRows === 0 && $processableRows > 0) {
            $rating = $this->downgradeRating($rating);
        }

        if ($invalidRows > 0 || $warningRows > 0) {
            $rating = $this->downgradeRating($rating);
        }

        return $rating;
    }

    protected function downgradeRating(string $rating): string
    {
        return match ($rating) {
            'Excellent' => 'Good',
            'Good' => 'Needs Review',
            default => $rating,
        };
    }
}
