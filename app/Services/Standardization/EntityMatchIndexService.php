<?php

namespace App\Services\Standardization;

use App\Models\Company;
use App\Models\CompanyAlias;
use App\Models\DrugAlias;
use App\Models\StandardizedDrug;
use Illuminate\Support\Collection;

/**
 * Scalable candidate lookup — avoids full-table scans for fuzzy matching.
 * Uses prefix indexing and batch-level caching for standardization runs.
 */
class EntityMatchIndexService
{
    /** @var array<string, Collection<int, StandardizedDrug>> */
    protected array $drugPrefixCache = [];

    /** @var array<string, Collection<int, Company>> */
    protected array $companyPrefixCache = [];

    /** @var Collection<int, StandardizedDrug>|null */
    protected ?Collection $allDrugsCache = null;

    /** @var Collection<int, Company>|null */
    protected ?Collection $allCompaniesCache = null;

    /** @var Collection<string, CompanyAlias>|null */
    protected ?Collection $companyAliasByNormalized = null;

    /** @var Collection<string, DrugAlias>|null */
    protected ?Collection $drugAliasByNormalized = null;

    /** @var array<string, Company> */
    protected array $companyByNormalizedKey = [];

    /** @var array<string, StandardizedDrug> */
    protected array $drugByCodeCache = [];

    /** @var array<string, Collection<int, StandardizedDrug>> */
    protected array $drugsByInnCache = [];

    protected bool $warmedUp = false;

    public function warmupCaches(): void
    {
        if ($this->warmedUp) {
            return;
        }

        $this->companyAliasByNormalized = CompanyAlias::query()
            ->with('company')
            ->get()
            ->keyBy('normalized_alias');

        Company::query()
            ->where('is_active', true)
            ->select(['id', 'name', 'normalized_name', 'country_id'])
            ->get()
            ->each(function (Company $company): void {
                $this->rememberCompany($company);
            });

        $this->drugAliasByNormalized = DrugAlias::query()
            ->with('standardizedDrug')
            ->get()
            ->keyBy('normalized_alias');

        StandardizedDrug::query()
            ->where('is_active', true)
            ->select(['id', 'code', 'inn', 'display_name', 'product_name_normalized', 'strength', 'form'])
            ->get()
            ->each(function (StandardizedDrug $drug): void {
                if ($drug->code !== null && $drug->code !== '') {
                    $this->drugByCodeCache[mb_strtoupper((string) $drug->code)] = $drug;
                }

                if ($drug->inn !== null && $drug->inn !== '') {
                    $innKey = mb_strtolower((string) $drug->inn);
                    $this->drugsByInnCache[$innKey] = ($this->drugsByInnCache[$innKey] ?? collect())
                        ->push($drug)
                        ->values();
                }
            });

        $this->warmedUp = true;
    }

    public function findCompanyAlias(string $normalizedAlias): ?CompanyAlias
    {
        $this->warmupCaches();

        return $this->companyAliasByNormalized?->get($normalizedAlias);
    }

    public function findCompanyByNormalizedName(string $normalizedName, ?int $countryId = null): ?Company
    {
        $this->warmupCaches();

        if ($countryId !== null) {
            $scoped = $this->companyByNormalizedKey[$this->companyKey($normalizedName, $countryId)] ?? null;

            if ($scoped !== null) {
                return $scoped;
            }
        }

        return $this->companyByNormalizedKey[$this->companyKey($normalizedName, null)] ?? null;
    }

    public function findDrugAlias(string $normalizedAlias): ?DrugAlias
    {
        $this->warmupCaches();

        return $this->drugAliasByNormalized?->get($normalizedAlias);
    }

    public function findDrugByCode(string $code): ?StandardizedDrug
    {
        $this->warmupCaches();

        return $this->drugByCodeCache[mb_strtoupper($code)] ?? null;
    }

    /**
     * @return Collection<int, StandardizedDrug>
     */
    public function findDrugsByInn(string $inn): Collection
    {
        $this->warmupCaches();

        return $this->drugsByInnCache[mb_strtolower($inn)] ?? collect();
    }

    /**
     * @return Collection<int, StandardizedDrug>
     */
    public function drugCandidates(?string $normalizedNeedle, int $maxCandidates = 50): Collection
    {
        if ($normalizedNeedle === null || $normalizedNeedle === '') {
            return collect();
        }

        $prefix = mb_substr($normalizedNeedle, 0, 3);

        if (isset($this->drugPrefixCache[$prefix])) {
            return $this->drugPrefixCache[$prefix]->take($maxCandidates);
        }

        $query = StandardizedDrug::query()->where('is_active', true);

        $query->where(function ($q) use ($prefix, $normalizedNeedle) {
            $q->where('product_name_normalized', 'like', $prefix.'%')
                ->orWhere('display_name', 'like', $prefix.'%')
                ->orWhereRaw('LOWER(inn) LIKE ?', [$prefix.'%']);

            if (strlen($normalizedNeedle) >= 4) {
                $q->orWhere('product_name_normalized', 'like', '%'.$normalizedNeedle.'%')
                    ->orWhereRaw('LOWER(inn) LIKE ?', ['%'.$normalizedNeedle.'%']);
            }
        });

        $candidates = $query->limit($maxCandidates)->get();
        $this->drugPrefixCache[$prefix] = $candidates;

        return $candidates;
    }

    /**
     * @return Collection<int, Company>
     */
    public function companyCandidates(?string $normalizedNeedle, ?int $countryId = null, int $maxCandidates = 50): Collection
    {
        if ($normalizedNeedle === null || $normalizedNeedle === '') {
            return collect();
        }

        $cacheKey = $prefix = mb_substr($normalizedNeedle, 0, 3).':'.($countryId ?? 'all');

        if (isset($this->companyPrefixCache[$cacheKey])) {
            return $this->companyPrefixCache[$cacheKey]->take($maxCandidates);
        }

        $query = Company::query()->where('is_active', true);

        if ($countryId !== null) {
            $query->where('country_id', $countryId);
        }

        $query->where(function ($q) use ($prefix, $normalizedNeedle) {
            $q->where('normalized_name', 'like', $prefix.'%')
                ->orWhere('name', 'like', $prefix.'%');

            if (strlen($normalizedNeedle) >= 4) {
                $q->orWhere('normalized_name', 'like', '%'.$normalizedNeedle.'%');
            }
        });

        $candidates = $query->limit($maxCandidates)->get();

        if ($candidates->isEmpty() && $countryId !== null) {
            return $this->companyCandidates($normalizedNeedle, null, $maxCandidates);
        }

        $this->companyPrefixCache[$cacheKey] = $candidates;

        return $candidates;
    }

    /**
     * Fallback when prefix search returns too few results.
     *
     * @return Collection<int, StandardizedDrug>
     */
    public function allActiveDrugs(): Collection
    {
        if ($this->allDrugsCache === null) {
            $this->allDrugsCache = StandardizedDrug::query()
                ->where('is_active', true)
                ->select(['id', 'code', 'inn', 'display_name', 'product_name_normalized', 'strength', 'form'])
                ->get();
        }

        return $this->allDrugsCache;
    }

    /**
     * @return Collection<int, Company>
     */
    public function allActiveCompanies(): Collection
    {
        if ($this->allCompaniesCache === null) {
            $this->allCompaniesCache = Company::query()
                ->where('is_active', true)
                ->select(['id', 'name', 'normalized_name', 'country_id'])
                ->get();
        }

        return $this->allCompaniesCache;
    }

    public function rememberCompany(Company $company): void
    {
        $this->companyByNormalizedKey[$this->companyKey((string) $company->normalized_name, null)] = $company;

        if ($company->country_id !== null) {
            $this->companyByNormalizedKey[$this->companyKey((string) $company->normalized_name, (int) $company->country_id)] = $company;
        }
    }

    public function rememberDrug(StandardizedDrug $drug): void
    {
        if ($drug->code !== null && $drug->code !== '') {
            $this->drugByCodeCache[mb_strtoupper((string) $drug->code)] = $drug;
        }
    }

    protected function companyKey(string $normalizedName, ?int $countryId): string
    {
        return ($countryId ?? 'all').':'.$normalizedName;
    }

    public function clearCache(): void
    {
        $this->drugPrefixCache = [];
        $this->companyPrefixCache = [];
        $this->allDrugsCache = null;
        $this->allCompaniesCache = null;
        $this->companyAliasByNormalized = null;
        $this->drugAliasByNormalized = null;
        $this->companyByNormalizedKey = [];
        $this->drugByCodeCache = [];
        $this->drugsByInnCache = [];
        $this->warmedUp = false;
    }
}
