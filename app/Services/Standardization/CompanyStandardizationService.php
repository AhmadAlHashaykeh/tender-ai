<?php

namespace App\Services\Standardization;

use App\Models\Company;
use App\Models\ImportRow;
use App\Support\Normalization\TextNormalizer;

class CompanyStandardizationService
{
    public function __construct(
        protected TextNormalizer $normalizer,
        protected FuzzyMatcherService $fuzzyMatcher,
        protected StandardizationSuggestionService $suggestionService,
        protected EntityMatchIndexService $matchIndex,
        protected CountryStandardizationService $countryService,
    ) {}

    /**
     * @return array{
     *     company_id: ?int,
     *     confidence: float,
     *     normalized: array<string, mixed>,
     *     match_type: ?string,
     *     suggested: ?array<string, mixed>,
     *     reason: ?string
     * }
     */
    public function standardize(ImportRow $row, bool $persistSuggestions = true, ?int $countryId = null): array
    {
        $companyRaw = $this->normalizer->trim($row->raw_company_name);
        $winnerRaw = $this->normalizer->trim($row->raw_winner);

        $sourceRaw = $companyRaw ?? $winnerRaw;
        $normalizedCompany = $this->normalizer->normalizeCompanyName($companyRaw);
        $normalizedWinner = $this->normalizer->normalizeCompanyName($winnerRaw);
        $normalizedSource = $normalizedCompany ?? $normalizedWinner;

        if ($normalizedSource === null) {
            return [
                'company_id' => null,
                'confidence' => 0.0,
                'normalized' => [
                    'raw_company_name' => $companyRaw,
                    'raw_winner' => $winnerRaw,
                    'normalized_name' => null,
                ],
                'match_type' => null,
                'suggested' => null,
                'reason' => 'No company or winner value',
            ];
        }

        if ($countryId === null) {
            $countryResult = $this->countryService->standardize($row);
            $countryId = $countryResult['country_id'];
        }

        $agreementBonus = ($normalizedCompany !== null
            && $normalizedWinner !== null
            && $normalizedCompany === $normalizedWinner) ? 5.0 : 0.0;

        $alias = $this->matchIndex->findCompanyAlias($normalizedSource);

        if ($alias !== null && $this->countryMatches($alias->company, $countryId)) {
            $confidence = min(100.0, 95.0 + $agreementBonus);

            return $this->matched(
                $alias->company_id,
                $confidence,
                $companyRaw,
                $winnerRaw,
                $normalizedSource,
                'exact_alias',
                $alias->company?->name,
                'Exact alias match'.($countryId ? ' with country context' : '')
            );
        }

        $company = $this->matchIndex->findCompanyByNormalizedName($normalizedSource, $countryId);

        if ($company === null && $countryId !== null) {
            $company = $this->matchIndex->findCompanyByNormalizedName($normalizedSource, null);
        }

        if ($company !== null && $this->countryMatches($company, $countryId)) {
            $confidence = min(100.0, 90.0 + $agreementBonus);

            return $this->matched(
                $company->id,
                $confidence,
                $companyRaw,
                $winnerRaw,
                $normalizedSource,
                'exact_name',
                $company->name,
                'Exact name match'.($countryId ? ' scoped to country' : '')
            );
        }

        $candidates = $this->matchIndex->companyCandidates($normalizedSource, $countryId);

        if ($candidates->isEmpty()) {
            $candidates = $this->matchIndex->companyCandidates($normalizedSource, null);
        }

        $candidateMap = [];
        foreach ($candidates as $candidate) {
            $candidateMap[$candidate->id] = $candidate->normalized_name;
        }

        $best = $this->fuzzyMatcher->bestMatch($normalizedSource, $candidateMap);

        if ($best !== null && $best['score'] >= 90) {
            $matched = $candidates->firstWhere('id', $best['key']);

            if ($this->countryMatches($matched, $countryId)) {
                return $this->matched(
                    $matched?->id,
                    min(100.0, 85.0 + $agreementBonus),
                    $companyRaw,
                    $winnerRaw,
                    $normalizedSource,
                    'fuzzy_high',
                    $matched?->name,
                    'Fuzzy match with country context'
                );
            }

            if ($countryId !== null) {
                return $this->reviewRequiredMatch(
                    $row,
                    $matched,
                    $best['score'],
                    $companyRaw,
                    $winnerRaw,
                    $normalizedSource,
                    $persistSuggestions,
                    'Name matches but country differs — may be separate entity (e.g. Hikma Jordan vs Hikma Algeria)'
                );
            }
        }

        if ($best !== null && $best['score'] >= 80) {
            $matched = $candidates->firstWhere('id', $best['key']);

            return $this->matched(
                $matched?->id,
                min(100.0, 70.0 + $agreementBonus),
                $companyRaw,
                $winnerRaw,
                $normalizedSource,
                'fuzzy_medium',
                $matched?->name,
                'Medium-confidence fuzzy match'
            );
        }

        $suggested = [
            'name' => $sourceRaw,
            'normalized_name' => $normalizedSource,
            'country_id' => $countryId,
        ];

        if ($persistSuggestions) {
            $this->suggestionService->storeSuggestion(
                $row,
                'company',
                $suggested,
                60.0,
                'rules',
                rationale: 'No existing company match; suggested new company name.'
            );
        }

        return [
            'company_id' => null,
            'confidence' => 60.0,
            'normalized' => [
                'raw_company_name' => $companyRaw,
                'raw_winner' => $winnerRaw,
                'normalized_name' => $normalizedSource,
                'suggested_name' => $sourceRaw,
                'country_id' => $countryId,
            ],
            'match_type' => 'suggested',
            'suggested' => $suggested,
            'reason' => 'No match found — review queue',
        ];
    }

    protected function countryMatches(?Company $company, ?int $countryId): bool
    {
        if ($countryId === null || $company === null) {
            return true;
        }

        if ($company->country_id === null) {
            return true;
        }

        return (int) $company->country_id === (int) $countryId;
    }

    /**
     * @return array<string, mixed>
     */
    protected function reviewRequiredMatch(
        ImportRow $row,
        ?Company $matched,
        float $score,
        ?string $companyRaw,
        ?string $winnerRaw,
        string $normalizedSource,
        bool $persistSuggestions,
        string $reason,
    ): array {
        if ($persistSuggestions && $matched !== null) {
            $this->suggestionService->storeSuggestion(
                $row,
                'company',
                [
                    'company_id' => $matched->id,
                    'name' => $matched->name,
                    'raw_name' => $companyRaw ?? $winnerRaw,
                    'fuzzy_score' => $score,
                ],
                65.0,
                'fuzzy',
                suggestedCompanyId: $matched->id,
                rationale: $reason
            );
        }

        return [
            'company_id' => null,
            'confidence' => 65.0,
            'normalized' => [
                'raw_company_name' => $companyRaw,
                'raw_winner' => $winnerRaw,
                'normalized_name' => $normalizedSource,
                'suggested_name' => $matched?->name,
                'suggested_company_id' => $matched?->id,
            ],
            'match_type' => 'country_mismatch',
            'suggested' => ['name' => $matched?->name, 'company_id' => $matched?->id],
            'reason' => $reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function matched(
        ?int $companyId,
        float $confidence,
        ?string $companyRaw,
        ?string $winnerRaw,
        string $normalizedSource,
        string $matchType,
        ?string $canonicalName,
        string $reason,
    ): array {
        return [
            'company_id' => $companyId,
            'confidence' => $confidence,
            'normalized' => [
                'raw_company_name' => $companyRaw,
                'raw_winner' => $winnerRaw,
                'normalized_name' => $normalizedSource,
                'canonical_name' => $canonicalName,
                'company_id' => $companyId,
            ],
            'match_type' => $matchType,
            'suggested' => null,
            'reason' => $reason,
        ];
    }
}
