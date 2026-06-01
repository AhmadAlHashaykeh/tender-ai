<?php

namespace App\Services\Standardization;

use App\Models\ImportRow;
use App\Support\Normalization\TextNormalizer;

class TenderStandardizationService
{
    public function __construct(
        protected TextNormalizer $normalizer,
    ) {}

    /**
     * @param  array{country_id: ?int, confidence: float}  $countryResult
     * @return array{
     *     confidence: float,
     *     normalized: array<string, mixed>,
     *     match_type: ?string,
     *     suggested: ?array<string, mixed>
     * }
     */
    public function standardize(ImportRow $row, array $countryResult): array
    {
        $tenderNumber = $this->normalizer->normalizeTenderNumber($row->raw_tender_number);
        $year = $this->parseYear($row->raw_year, $row->normalized_data ?? []);
        $version = $this->normalizer->normalizeBasic($row->raw_version);
        $countryId = $countryResult['country_id'] ?? null;

        $normalized = [
            'tender_number' => $tenderNumber,
            'year' => $year,
            'version' => $version,
            'country_id' => $countryId,
        ];

        if ($tenderNumber === null) {
            return [
                'confidence' => 30.0,
                'normalized' => $normalized,
                'match_type' => 'warning_missing_number',
                'suggested' => [
                    'message' => 'Tender number missing; identity weak.',
                ],
            ];
        }

        if ($countryId !== null && $year !== null) {
            return [
                'confidence' => 90.0,
                'normalized' => $normalized,
                'match_type' => 'strong_identity',
                'suggested' => [
                    'tender_number' => $tenderNumber,
                    'country_id' => $countryId,
                    'year' => $year,
                    'version' => $version,
                ],
            ];
        }

        if ($countryId !== null) {
            return [
                'confidence' => 75.0,
                'normalized' => $normalized,
                'match_type' => 'number_country',
                'suggested' => [
                    'tender_number' => $tenderNumber,
                    'country_id' => $countryId,
                    'year' => $year,
                    'version' => $version,
                ],
            ];
        }

        return [
            'confidence' => 60.0,
            'normalized' => $normalized,
            'match_type' => 'number_only',
            'suggested' => [
                'tender_number' => $tenderNumber,
                'year' => $year,
                'version' => $version,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $existingNormalized
     */
    protected function parseYear(?string $rawYear, ?array $existingNormalized): ?int
    {
        if (isset($existingNormalized['year']) && is_numeric($existingNormalized['year'])) {
            return (int) $existingNormalized['year'];
        }

        if ($rawYear === null || trim($rawYear) === '') {
            return null;
        }

        if (preg_match('/(19|20)\d{2}/', $rawYear, $matches)) {
            return (int) $matches[0];
        }

        if (is_numeric($rawYear)) {
            $year = (int) $rawYear;

            return ($year >= 1900 && $year <= 2100) ? $year : null;
        }

        return null;
    }
}
