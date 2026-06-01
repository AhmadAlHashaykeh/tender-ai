<?php

namespace App\Services\Import;

use App\Services\Standardization\FuzzyMatcherService;
use Illuminate\Support\Str;

class ColumnMappingService
{
    public function __construct(
        protected FuzzyMatcherService $fuzzyMatcher,
    ) {}

    /**
     * @param  array<int, string>  $headers  Raw header row from spreadsheet
     * @param  array<string, int|null>|null  $userOverrides  canonical => column index
     * @return array{
     *     detected_headers: array<int, string>,
     *     mappings: array<int, array{header: string, column_index: int, canonical_field: ?string, confidence: float, match_type: string, reason: string}>,
     *     mapped_headers: array<string, int|null>,
     *     missing_required: list<string>,
     *     missing_drug_identity: bool,
     *     extra_columns: list<string>,
     *     ignored_columns: list<string>,
     *     overall_confidence: float,
     *     can_proceed: bool
     * }
     */
    public function detectMapping(array $headers, ?array $userOverrides = null): array
    {
        $detectedHeaders = $this->normalizeHeaderRow($headers);
        $canonicalFields = config('import.canonical_fields', []);
        $aliases = config('import.column_aliases', config('import.expected_columns', []));
        $mappedHeaders = array_fill_keys($canonicalFields, null);
        $mappings = [];
        $usedIndices = [];

        if ($userOverrides !== null) {
            foreach ($userOverrides as $canonical => $index) {
                if ($index !== null && isset($detectedHeaders[$index])) {
                    $mappedHeaders[$canonical] = $index;
                    $usedIndices[$index] = true;
                }
            }
        }

        foreach ($detectedHeaders as $index => $header) {
            if ($header === '') {
                continue;
            }

            if ($userOverrides !== null) {
                $canonical = array_search($index, $userOverrides, true);
                if ($canonical !== false) {
                    $mappings[] = [
                        'header' => $header,
                        'column_index' => $index,
                        'canonical_field' => $canonical,
                        'confidence' => 100.0,
                        'match_type' => 'manual',
                        'reason' => 'User selected mapping',
                    ];

                    continue;
                }
            }

            $match = $this->matchHeader($header, $aliases, $mappedHeaders);

            if ($match !== null) {
                $mappedHeaders[$match['canonical']] = $index;
                $usedIndices[$index] = true;
                $mappings[] = [
                    'header' => $header,
                    'column_index' => $index,
                    'canonical_field' => $match['canonical'],
                    'confidence' => $match['confidence'],
                    'match_type' => $match['match_type'],
                    'reason' => $match['reason'],
                ];
            }
        }

        $extraColumns = [];
        foreach ($detectedHeaders as $index => $header) {
            if ($header === '' || isset($usedIndices[$index])) {
                continue;
            }

            $extraColumns[] = $header;
            $mappings[] = [
                'header' => $header,
                'column_index' => $index,
                'canonical_field' => null,
                'confidence' => 0.0,
                'match_type' => 'additional',
                'reason' => 'Additional Information — not mapped to a canonical field',
            ];
        }

        $missingRequired = $this->missingRequiredFields($mappedHeaders);
        $missingDrugIdentity = ! $this->hasDrugIdentityMapped($mappedHeaders);
        $ignoredColumns = [];

        $confidences = array_filter(
            array_column($mappings, 'confidence'),
            fn ($c) => $c > 0
        );
        $overallConfidence = $confidences !== []
            ? round(array_sum($confidences) / count($confidences), 2)
            : 0.0;

        return [
            'detected_headers' => $detectedHeaders,
            'mappings' => $mappings,
            'mapped_headers' => $mappedHeaders,
            'missing_required' => $missingRequired,
            'missing_drug_identity' => $missingDrugIdentity,
            'extra_columns' => $extraColumns,
            'ignored_columns' => $ignoredColumns,
            'overall_confidence' => $overallConfidence,
            'can_proceed' => $missingRequired === [] && ! $missingDrugIdentity,
        ];
    }

    /**
     * @param  array<string, array<int, string>>  $aliases
     * @param  array<string, int|null>  $alreadyMapped
     * @return array{canonical: string, confidence: float, match_type: string, reason: string}|null
     */
    protected function matchHeader(string $header, array $aliases, array $alreadyMapped): ?array
    {
        $normalizedHeader = $this->normalizeHeaderKey($header);
        $bestMatch = null;

        foreach ($aliases as $canonical => $aliasList) {
            if (($alreadyMapped[$canonical] ?? null) !== null) {
                continue;
            }

            if ($normalizedHeader === $this->normalizeHeaderKey($canonical)) {
                return [
                    'canonical' => $canonical,
                    'confidence' => 100.0,
                    'match_type' => 'exact',
                    'reason' => 'Exact match to canonical field name',
                ];
            }

            foreach ($aliasList as $alias) {
                if ($normalizedHeader === $this->normalizeHeaderKey($alias)) {
                    return [
                        'canonical' => $canonical,
                        'confidence' => 100.0,
                        'match_type' => 'alias',
                        'reason' => 'Matched known alias "'.$alias.'"',
                    ];
                }
            }

            $candidates = [$this->normalizeHeaderKey($canonical)];
            foreach ($aliasList as $alias) {
                $candidates[] = $this->normalizeHeaderKey($alias);
            }

            $fuzzy = $this->fuzzyMatcher->bestMatch($normalizedHeader, array_unique($candidates));
            $minScore = (float) config('import.fuzzy_header_match_min', 78);

            if ($fuzzy !== null && $fuzzy['score'] >= $minScore) {
                if ($bestMatch === null || $fuzzy['score'] > $bestMatch['confidence']) {
                    $bestMatch = [
                        'canonical' => $canonical,
                        'confidence' => $fuzzy['score'],
                        'match_type' => 'fuzzy',
                        'reason' => 'Fuzzy match (similarity '.$fuzzy['score'].'%)',
                    ];
                }
            }
        }

        return $bestMatch;
    }

    /**
     * @param  array<string, int|null>  $mappedHeaders
     * @return list<string>
     */
    public function missingRequiredFields(array $mappedHeaders): array
    {
        $labels = config('import.header_labels', []);
        $missing = [];

        foreach (config('import.required_canonical_fields', []) as $field) {
            if (($mappedHeaders[$field] ?? null) === null) {
                $missing[] = $labels[$field] ?? $field;
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, int|null>  $mappedHeaders
     */
    public function hasDrugIdentityMapped(array $mappedHeaders): bool
    {
        foreach (config('import.drug_identity_fields', []) as $field) {
            if (($mappedHeaders[$field] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<int, string>
     */
    public function normalizeHeaderRow(array $headers): array
    {
        return array_map(fn ($value) => trim((string) $value), $headers);
    }

    public function normalizeHeaderKey(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->toString();
    }

    /**
     * @return list<string>
     */
    public function requiredHeaderLabels(): array
    {
        $labels = config('import.header_labels', []);

        return array_values(array_intersect_key(
            $labels,
            array_flip(config('import.required_canonical_fields', []))
        ));
    }

    /**
     * @return list<string>
     */
    public function allCanonicalLabels(): array
    {
        return array_values(config('import.header_labels', []));
    }

    /**
     * Normalize legacy qty key to quantity for internal pipeline use.
     *
     * @param  array<string, mixed>  $canonical
     * @return array<string, mixed>
     */
    public function normalizeCanonicalKeys(array $canonical): array
    {
        if (array_key_exists('qty', $canonical) && ! array_key_exists('quantity', $canonical)) {
            $canonical['quantity'] = $canonical['qty'];
            unset($canonical['qty']);
        }

        return $canonical;
    }
}
