<?php

namespace App\Services\Standardization;

use App\Models\Country;
use App\Models\ImportRow;
use App\Support\Normalization\TextNormalizer;

class CountryStandardizationService
{
    public function __construct(
        protected TextNormalizer $normalizer,
        protected FuzzyMatcherService $fuzzyMatcher,
    ) {}

    /** @var \Illuminate\Support\Collection<int, Country>|null */
    protected static $countryCache = null;

    /**
     * @return array{
     *     country_id: ?int,
     *     confidence: float,
     *     normalized: array<string, mixed>,
     *     match_type: ?string,
     *     reason: ?string,
     *     review_required: bool
     * }
     */
    public function standardize(ImportRow $row): array
    {
        $raw = $row->raw_country;
        $normalizedName = $this->normalizer->normalizeCountry($raw);

        if ($normalizedName === null) {
            return [
                'country_id' => null,
                'confidence' => 0.0,
                'normalized' => [
                    'raw' => $raw,
                    'normalized_name' => null,
                ],
                'match_type' => null,
                'reason' => 'Empty country value',
                'review_required' => true,
            ];
        }

        $countries = $this->activeCountries();

        foreach ($countries as $country) {
            if (mb_strtolower($country->name) === $normalizedName) {
                return $this->result($country->id, 100.0, $raw, $normalizedName, $country->name, 'exact_name', 'Exact country name match');
            }

            if (mb_strtolower((string) $country->code) === $normalizedName
                || mb_strtolower((string) $country->iso_code_2) === $normalizedName
                || mb_strtolower((string) $country->iso_code_3) === $normalizedName) {
                return $this->result($country->id, 95.0, $raw, $normalizedName, $country->name, 'alias_code', 'Matched country code or ISO');
            }
        }

        $aliasResolved = $this->resolveViaAlias($raw, $normalizedName, $countries);
        if ($aliasResolved !== null) {
            return $aliasResolved;
        }

        $candidates = [];
        foreach ($countries as $country) {
            $candidates[$country->id] = mb_strtolower($country->name);
        }

        $best = $this->fuzzyMatcher->bestMatch($normalizedName, $candidates);

        if ($best !== null && $best['score'] >= 94) {
            $country = $countries->firstWhere('id', $best['key']);

            return $this->result(
                $country?->id,
                $best['score'],
                $raw,
                $normalizedName,
                $country?->name,
                'fuzzy_name',
                'Alias + Fuzzy Match',
                reviewRequired: $best['score'] < 90,
            );
        }

        if ($best !== null && $best['score'] >= 80) {
            $country = $countries->firstWhere('id', $best['key']);

            return $this->result(
                $country?->id,
                $best['score'],
                $raw,
                $normalizedName,
                $country?->name,
                'fuzzy_low',
                'Low-confidence fuzzy match — review required',
                reviewRequired: true,
            );
        }

        return [
            'country_id' => null,
            'confidence' => max(40.0, $best['score'] ?? 40.0),
            'normalized' => [
                'raw' => $raw,
                'normalized_name' => $normalizedName,
                'suggested_name' => $best !== null ? $countries->firstWhere('id', $best['key'])?->name : null,
            ],
            'match_type' => 'unmatched',
            'reason' => 'Unknown country — not in controlled list; sent to review queue',
            'review_required' => true,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Country>  $countries
     * @return array<string, mixed>|null
     */
    protected function resolveViaAlias(?string $raw, string $normalizedName, $countries): ?array
    {
        $aliases = config('import.country_aliases', []);

        foreach ($aliases as $alias => $canonical) {
            if ($normalizedName === $canonical || $normalizedName === $alias) {
                foreach ($countries as $country) {
                    if (mb_strtolower($country->name) === $canonical) {
                        return $this->result(
                            $country->id,
                            98.0,
                            $raw,
                            $normalizedName,
                            $country->name,
                            'config_alias',
                            'Matched configured country alias "'.$alias.'"'
                        );
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Country>
     */
    protected function activeCountries()
    {
        if (self::$countryCache === null) {
            self::$countryCache = Country::query()->where('is_active', true)->get();
        }

        return self::$countryCache;
    }

    /**
     * @return array<string, mixed>
     */
    protected function result(
        ?int $countryId,
        float $confidence,
        ?string $raw,
        string $normalizedName,
        ?string $canonicalName,
        string $matchType,
        string $reason,
        bool $reviewRequired = false,
    ): array {
        return [
            'country_id' => $countryId,
            'confidence' => $confidence,
            'normalized' => [
                'raw' => $raw,
                'normalized_name' => $normalizedName,
                'canonical_name' => $canonicalName,
                'country_id' => $countryId,
            ],
            'match_type' => $matchType,
            'reason' => $reason,
            'review_required' => $reviewRequired,
        ];
    }
}
