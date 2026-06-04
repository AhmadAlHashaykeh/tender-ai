<?php

namespace App\Services\Standardization;

use App\Models\Country;
use App\Models\ImportRow;
use App\Models\Region;
use App\Support\Normalization\TextNormalizer;

class CountryStandardizationService
{
    public function __construct(
        protected TextNormalizer $normalizer,
        protected FuzzyMatcherService $fuzzyMatcher,
    ) {}

    /** @var \Illuminate\Support\Collection<int, Country>|null */
    protected static $countryCache = null;

    /** @var \Illuminate\Support\Collection<int, Region>|null */
    protected static $regionCache = null;

    public static function clearCache(): void
    {
        self::$countryCache = null;
        self::$regionCache = null;
    }

    /**
     * @return array{
     *     country_id: ?int,
     *     region_id: ?int,
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
            return $this->unmatched($raw, null, 'Empty country value');
        }

        $countries = $this->activeCountries();

        foreach ($countries as $country) {
            if (mb_strtolower($country->name) === $normalizedName) {
                return $this->countryResult($country, 100.0, $raw, $normalizedName, 'exact_name', 'Exact country name match');
            }

            if (mb_strtolower((string) $country->code) === $normalizedName
                || mb_strtolower((string) $country->iso_code_2) === $normalizedName
                || mb_strtolower((string) $country->iso_code_3) === $normalizedName) {
                return $this->countryResult($country, 95.0, $raw, $normalizedName, 'alias_code', 'Matched country code or ISO');
            }
        }

        $canonical = $this->resolveCanonicalCountryName($raw, $normalizedName);
        if ($canonical !== null) {
            foreach ($countries as $country) {
                if (mb_strtolower($country->name) === $canonical) {
                    return $this->countryResult($country, 98.0, $raw, $normalizedName, 'config_alias', 'Matched configured country alias');
                }
            }
        }

        $regionResolved = $this->resolveViaRegion($raw, $normalizedName);
        if ($regionResolved !== null) {
            return $regionResolved;
        }

        $candidates = [];
        foreach ($countries as $country) {
            $candidates[$country->id] = mb_strtolower($country->name);
        }

        $best = $this->fuzzyMatcher->bestMatch($normalizedName, $candidates);

        if ($best !== null && $best['score'] >= 94) {
            $country = $countries->firstWhere('id', $best['key']);

            return $this->countryResult(
                $country,
                $best['score'],
                $raw,
                $normalizedName,
                'fuzzy_name',
                'Alias + Fuzzy Match',
                reviewRequired: $best['score'] < 90,
            );
        }

        if ($best !== null && $best['score'] >= 80) {
            $country = $countries->firstWhere('id', $best['key']);

            return $this->countryResult(
                $country,
                $best['score'],
                $raw,
                $normalizedName,
                'fuzzy_low',
                'Low-confidence fuzzy match — review required',
                reviewRequired: true,
            );
        }

        return $this->unmatched(
            $raw,
            $normalizedName,
            'Unknown country — not in controlled list; sent to review queue',
            $best,
            $countries,
        );
    }

    protected function resolveCanonicalCountryName(?string $raw, string $normalizedName): ?string
    {
        $aliases = config('import.country_aliases', []);
        $basic = $this->normalizer->normalizeBasic($raw);

        foreach (array_filter([$normalizedName, $basic]) as $candidate) {
            if (isset($aliases[$candidate])) {
                return $aliases[$candidate];
            }

            foreach ($aliases as $alias => $canonical) {
                if ($candidate === $alias || $candidate === $canonical) {
                    return $canonical;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveViaRegion(?string $raw, string $normalizedName): ?array
    {
        $aliases = config('import.country_region_aliases', []);
        $basic = $this->normalizer->normalizeBasic($raw);
        $regionCode = null;

        foreach (array_filter([$normalizedName, $basic]) as $candidate) {
            if (isset($aliases[$candidate])) {
                $regionCode = $aliases[$candidate];
                break;
            }

            foreach ($aliases as $alias => $code) {
                if ($candidate === $alias) {
                    $regionCode = $code;
                    break 2;
                }
            }
        }

        if ($regionCode === null) {
            return null;
        }

        $region = $this->activeRegions()->first(fn (Region $r) => mb_strtoupper((string) $r->code) === mb_strtoupper($regionCode));

        if ($region === null) {
            return null;
        }

        return [
            'country_id' => null,
            'region_id' => $region->id,
            'confidence' => 90.0,
            'normalized' => [
                'raw' => $raw,
                'normalized_name' => $normalizedName,
                'canonical_name' => $region->name,
                'region_id' => $region->id,
                'region_code' => $region->code,
                'country_id' => null,
            ],
            'match_type' => 'region_only',
            'reason' => sprintf(
                'Regional tender (%s) — no country entity mapped; assign a country or add a dedicated market',
                $region->name
            ),
            'review_required' => true,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Country>  $countries
     * @return array<string, mixed>
     */
    protected function unmatched(
        ?string $raw,
        ?string $normalizedName,
        string $reason,
        ?array $best = null,
        $countries = null,
    ): array {
        $suggested = null;
        if ($best !== null && $countries !== null) {
            $suggested = $countries->firstWhere('id', $best['key'])?->name;
        }

        return [
            'country_id' => null,
            'region_id' => null,
            'confidence' => max(40.0, $best['score'] ?? 40.0),
            'normalized' => [
                'raw' => $raw,
                'normalized_name' => $normalizedName,
                'suggested_name' => $suggested,
                'country_id' => null,
                'region_id' => null,
            ],
            'match_type' => 'unmatched',
            'reason' => $reason,
            'review_required' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function countryResult(
        ?Country $country,
        float $confidence,
        ?string $raw,
        string $normalizedName,
        string $matchType,
        string $reason,
        bool $reviewRequired = false,
    ): array {
        return [
            'country_id' => $country?->id,
            'region_id' => $country?->region_id,
            'confidence' => $confidence,
            'normalized' => [
                'raw' => $raw,
                'normalized_name' => $normalizedName,
                'canonical_name' => $country?->name,
                'country_id' => $country?->id,
                'region_id' => $country?->region_id,
            ],
            'match_type' => $matchType,
            'reason' => $reason,
            'review_required' => $reviewRequired,
        ];
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
     * @return \Illuminate\Support\Collection<int, Region>
     */
    protected function activeRegions()
    {
        if (self::$regionCache === null) {
            self::$regionCache = Region::query()->where('is_active', true)->get();
        }

        return self::$regionCache;
    }
}
