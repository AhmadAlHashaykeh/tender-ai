<?php

namespace App\Services\Standardization;

use App\Models\ImportRow;
use App\Models\StandardizedDrug;
use App\Support\Normalization\TextNormalizer;

class DrugStandardizationService
{
    public function __construct(
        protected TextNormalizer $normalizer,
        protected FuzzyMatcherService $fuzzyMatcher,
        protected StandardizationSuggestionService $suggestionService,
        protected EntityMatchIndexService $matchIndex,
    ) {}

    /**
     * @return array{
     *     standardized_drug_id: ?int,
     *     confidence: float,
     *     confidence_breakdown: array<string, float>,
     *     normalized: array<string, mixed>,
     *     match_type: ?string,
     *     suggested: ?array<string, mixed>
     * }
     */
    public function standardize(ImportRow $row, bool $persistSuggestions = true): array
    {
        $code = $this->normalizer->normalizeDrugCode($row->raw_code);
        $inn = $this->normalizer->normalizeDrugInn($row->raw_inn);
        $productName = $this->normalizer->normalizeDrugProductName($row->raw_product_name);
        $components = $this->normalizer->extractDrugComponents($row->raw_product_name ?? $row->raw_inn);

        if ($code === null && $inn === null && $productName === null) {
            return $this->emptyResult($components);
        }

        if ($code !== null) {
            $byCode = $this->matchIndex->findDrugByCode($code);

            if ($byCode !== null) {
                return $this->matched(
                    $byCode,
                    $this->buildBreakdown(inn: 100, strength: 100, form: 100, productName: 100, final: 95),
                    95.0,
                    $code,
                    $inn,
                    $productName,
                    $components,
                    'exact_code'
                );
            }
        }

        $aliasKeys = array_filter([$productName, $inn, $code]);
        foreach ($aliasKeys as $aliasKey) {
            $alias = $this->matchIndex->findDrugAlias($aliasKey);

            if ($alias?->standardizedDrug !== null) {
                return $this->matched(
                    $alias->standardizedDrug,
                    $this->buildBreakdown(inn: 100, strength: 90, form: 90, productName: 95, final: 90),
                    90.0,
                    $code,
                    $inn,
                    $productName,
                    $components,
                    'exact_alias'
                );
            }
        }

        if ($inn !== null) {
            $innMatch = $this->matchByInnFirst($inn, $productName, $components);

            if ($innMatch !== null) {
                return $this->matched(
                    $innMatch['drug'],
                    $innMatch['breakdown'],
                    $innMatch['confidence'],
                    $code,
                    $inn,
                    $productName,
                    $components,
                    $innMatch['match_type']
                );
            }
        }

        $needle = $productName ?? $inn ?? $code;
        $candidates = $this->matchIndex->drugCandidates($needle);

        if ($candidates->isEmpty() && $needle !== null) {
            $candidates = $this->matchIndex->allActiveDrugs()->take(50);
        }

        if ($needle !== null && $candidates->isNotEmpty()) {
            $candidateMap = [];
            foreach ($candidates as $drug) {
                $candidateMap[$drug->id] = $drug->product_name_normalized
                    ?? $this->normalizer->normalizeDrugProductName($drug->display_name)
                    ?? '';
            }

            $best = $this->fuzzyMatcher->bestMatch($needle, $candidateMap);

            if ($best !== null && $best['score'] >= 92) {
                $matched = $candidates->firstWhere('id', $best['key']);
                $breakdown = $this->scoreDrugMatch($matched, $inn, $productName, $components, $best['score']);

                return $this->matched($matched, $breakdown, $breakdown['final'], $code, $inn, $productName, $components, 'fuzzy_high');
            }

            if ($best !== null && $best['score'] >= 80) {
                $matched = $candidates->firstWhere('id', $best['key']);
                $breakdown = $this->scoreDrugMatch($matched, $inn, $productName, $components, $best['score']);

                if ($persistSuggestions && $matched !== null) {
                    $this->suggestionService->storeSuggestion(
                        $row,
                        'drug',
                        [
                            'standardized_drug_id' => $matched->id,
                            'display_name' => $matched->display_name,
                            'raw_product_name' => $row->raw_product_name,
                            'fuzzy_score' => $best['score'],
                            'confidence_breakdown' => $breakdown,
                        ],
                        $breakdown['final'],
                        'fuzzy',
                        suggestedDrugId: $matched->id,
                        rationale: 'Fuzzy drug match requires review.'
                    );
                }

                return $this->matched($matched, $breakdown, $breakdown['final'], $code, $inn, $productName, $components, 'fuzzy_medium');
            }
        }

        if ($code !== null && $inn !== null && $productName !== null) {
            return $this->suggestedPayload($row, 60.0, $code, $inn, $productName, $components, $persistSuggestions);
        }

        if ($productName !== null) {
            $quality = strlen($productName) >= 8 ? 60.0 : 45.0;

            return $this->suggestedPayload($row, $quality, $code, $inn, $productName, $components, $persistSuggestions);
        }

        return $this->suggestedPayload($row, 45.0, $code, $inn, $productName, $components, $persistSuggestions);
    }

    /**
     * INN-first matching strategy with component scoring.
     *
     * @return array{drug: StandardizedDrug, breakdown: array<string, float>, confidence: float, match_type: string}|null
     */
    protected function matchByInnFirst(?string $inn, ?string $productName, array $components): ?array
    {
        if ($inn === null) {
            return null;
        }

        $innMatch = $this->matchIndex->findDrugsByInn($inn);

        if ($innMatch->isEmpty()) {
            return null;
        }

        $bestCandidate = null;
        $bestBreakdown = null;
        $bestScore = 0.0;

        foreach ($innMatch as $candidate) {
            $target = $candidate->product_name_normalized
                ?? $this->normalizer->normalizeDrugProductName($candidate->display_name);
            $productScore = $productName !== null && $target !== null
                ? $this->fuzzyMatcher->similarity($productName, (string) $target)
                : 100.0;

            $breakdown = $this->scoreDrugMatch($candidate, $inn, $productName, $components, $productScore);

            if ($breakdown['final'] > $bestScore) {
                $bestScore = $breakdown['final'];
                $bestCandidate = $candidate;
                $bestBreakdown = $breakdown;
            }
        }

        if ($bestCandidate !== null && $bestScore >= 75) {
            return [
                'drug' => $bestCandidate,
                'breakdown' => $bestBreakdown,
                'confidence' => $bestScore,
                'match_type' => $bestScore >= 85 ? 'inn_product_similar' : 'inn_partial',
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $components
     * @return array{inn: float, strength: float, form: float, product_name: float, final: float}
     */
    protected function scoreDrugMatch(
        ?StandardizedDrug $drug,
        ?string $inn,
        ?string $productName,
        array $components,
        float $baseProductScore,
    ): array {
        $innScore = 100.0;
        if ($inn !== null && $drug?->inn !== null) {
            $innScore = $this->fuzzyMatcher->similarity($inn, mb_strtolower($drug->inn));
        }

        $strengthScore = 100.0;
        if (($components['strength'] ?? null) !== null && $drug?->strength !== null) {
            $strengthScore = $components['strength'] === $drug->strength ? 100.0 : 70.0;
        }

        $formScore = 100.0;
        if (($components['form'] ?? null) !== null && $drug?->form !== null) {
            $formScore = $this->fuzzyMatcher->similarity(
                (string) $components['form'],
                mb_strtolower((string) $drug->form)
            );
        }

        $productNameScore = $baseProductScore;

        $final = round(
            ($innScore * 0.35) + ($strengthScore * 0.20) + ($formScore * 0.15) + ($productNameScore * 0.30),
            2
        );

        return $this->buildBreakdown($innScore, $strengthScore, $formScore, $productNameScore, $final);
    }

    /**
     * @return array{inn: float, strength: float, form: float, product_name: float, final: float}
     */
    protected function buildBreakdown(
        float $inn,
        float $strength,
        float $form,
        float $productName,
        float $final,
    ): array {
        return [
            'inn' => round($inn, 2),
            'strength' => round($strength, 2),
            'form' => round($form, 2),
            'product_name' => round($productName, 2),
            'final' => round($final, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $components
     * @return array<string, mixed>
     */
    protected function emptyResult(array $components): array
    {
        return [
            'standardized_drug_id' => null,
            'confidence' => 0.0,
            'confidence_breakdown' => $this->buildBreakdown(0, 0, 0, 0, 0),
            'normalized' => [
                'code' => null,
                'inn' => null,
                'product_name' => null,
                'components' => $components,
            ],
            'match_type' => null,
            'suggested' => null,
        ];
    }

    /**
     * @param  array<string, float>  $breakdown
     * @param  array<string, mixed>  $components
     * @return array<string, mixed>
     */
    protected function matched(
        ?StandardizedDrug $drug,
        array $breakdown,
        float $confidence,
        ?string $code,
        ?string $inn,
        ?string $productName,
        array $components,
        string $matchType,
    ): array {
        return [
            'standardized_drug_id' => $drug?->id,
            'confidence' => $confidence,
            'confidence_breakdown' => $breakdown,
            'normalized' => [
                'code' => $code,
                'inn' => $inn,
                'product_name' => $productName,
                'components' => $components,
                'standardized_drug_id' => $drug?->id,
                'display_name' => $drug?->display_name,
                'confidence_breakdown' => $breakdown,
            ],
            'match_type' => $matchType,
            'suggested' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $components
     * @return array<string, mixed>
     */
    protected function suggestedPayload(
        ImportRow $row,
        float $confidence,
        ?string $code,
        ?string $inn,
        ?string $productName,
        array $components,
        bool $persistSuggestions,
    ): array {
        $breakdown = $this->buildBreakdown(
            inn: $inn !== null ? 50.0 : 0.0,
            strength: ($components['strength'] ?? null) !== null ? 60.0 : 0.0,
            form: ($components['form'] ?? null) !== null ? 60.0 : 0.0,
            productName: $productName !== null ? 55.0 : 0.0,
            final: $confidence,
        );

        $suggested = [
            'code' => $code,
            'inn' => $inn,
            'display_name' => $row->raw_product_name ?? $row->raw_inn ?? $code,
            'product_name_normalized' => $productName,
            'strength' => $components['strength'] ?? null,
            'strength_unit' => $components['strength_unit'] ?? null,
            'form' => $components['form'] ?? null,
            'confidence_breakdown' => $breakdown,
        ];

        if ($persistSuggestions) {
            $this->suggestionService->storeSuggestion(
                $row,
                'drug',
                $suggested,
                $confidence,
                'rules',
                rationale: 'No standardized drug match; suggested payload for review.'
            );
        }

        return [
            'standardized_drug_id' => null,
            'confidence' => $confidence,
            'confidence_breakdown' => $breakdown,
            'normalized' => [
                'code' => $code,
                'inn' => $inn,
                'product_name' => $productName,
                'components' => $components,
                'suggested' => $suggested,
                'confidence_breakdown' => $breakdown,
            ],
            'match_type' => 'suggested',
            'suggested' => $suggested,
        ];
    }
}
