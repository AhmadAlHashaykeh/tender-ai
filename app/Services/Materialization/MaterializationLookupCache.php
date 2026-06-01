<?php

namespace App\Services\Materialization;

use App\Models\BidRecord;
use App\Models\Company;
use App\Models\Country;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Services\Standardization\EntityMatchIndexService;
use Illuminate\Support\Collection;

/**
 * Per-chunk lookup cache to avoid repeated DB queries during materialization.
 */
class MaterializationLookupCache
{
    /** @var array<int, true> */
    protected array $materializedRowIds = [];

    /** @var array<string, int> */
    protected array $tenderByKey = [];

    /** @var array<int, Country> */
    protected array $countriesById = [];

    /** @var array<string, true> */
    protected array $companyAliasKeys = [];

    /** @var array<string, true> */
    protected array $drugAliasKeys = [];

    public function __construct(
        protected EntityMatchIndexService $matchIndex,
    ) {}

    public function warmup(): void
    {
        $this->matchIndex->warmupCaches();
    }

    public function clear(): void
    {
        $this->materializedRowIds = [];
        $this->tenderByKey = [];
        $this->countriesById = [];
        $this->companyAliasKeys = [];
        $this->drugAliasKeys = [];
        $this->matchIndex->clearCache();
    }

    /**
     * @param  Collection<int, int>|array<int, int>  $importRowIds
     */
    public function seedMaterializedRowIds(Collection|array $importRowIds): void
    {
        $ids = $importRowIds instanceof Collection ? $importRowIds->all() : $importRowIds;

        if ($ids === []) {
            return;
        }

        foreach (array_chunk($ids, 500) as $chunk) {
            BidRecord::query()
                ->whereIn('source_import_row_id', $chunk)
                ->pluck('source_import_row_id')
                ->each(fn ($id) => $this->materializedRowIds[(int) $id] = true);
        }
    }

    public function isRowMaterialized(int $importRowId): bool
    {
        return isset($this->materializedRowIds[$importRowId]);
    }

    public function markRowMaterialized(int $importRowId): void
    {
        $this->materializedRowIds[$importRowId] = true;
    }

    public function findCompanyByNormalizedName(string $normalizedName, ?int $countryId = null): ?Company
    {
        return $this->matchIndex->findCompanyByNormalizedName($normalizedName, $countryId);
    }

    public function rememberCompany(Company $company): void
    {
        $this->matchIndex->rememberCompany($company);
    }

    public function findDrugByCode(string $code): ?StandardizedDrug
    {
        return $this->matchIndex->findDrugByCode($code);
    }

    public function rememberDrug(StandardizedDrug $drug): void
    {
        $this->matchIndex->rememberDrug($drug);
    }

    /**
     * @param  list<int>  $countryIds
     */
    public function preloadTendersForCountries(array $countryIds): void
    {
        if ($countryIds === []) {
            return;
        }

        Tender::query()
            ->whereIn('country_id', $countryIds)
            ->select(['id', 'tender_number', 'country_id', 'year', 'version'])
            ->get()
            ->each(function (Tender $tender): void {
                $this->tenderByKey[$this->tenderKey(
                    $tender->tender_number,
                    (int) $tender->country_id,
                    $tender->year !== null ? (int) $tender->year : null,
                    $tender->version,
                )] = (int) $tender->id;
            });
    }

    public function findTenderId(
        string $tenderNumber,
        int $countryId,
        ?int $year,
        ?string $version,
    ): ?int {
        return $this->tenderByKey[$this->tenderKey($tenderNumber, $countryId, $year, $version)] ?? null;
    }

    public function rememberTender(
        string $tenderNumber,
        int $countryId,
        ?int $year,
        ?string $version,
        int $tenderId,
    ): void {
        $this->tenderByKey[$this->tenderKey($tenderNumber, $countryId, $year, $version)] = $tenderId;
    }

    public function country(int $countryId): ?Country
    {
        if (isset($this->countriesById[$countryId])) {
            return $this->countriesById[$countryId];
        }

        $country = Country::query()->find($countryId);

        if ($country !== null) {
            $this->countriesById[$countryId] = $country;
        }

        return $country;
    }

    public function hasCompanyAlias(int $companyId, string $normalizedAlias): bool
    {
        return isset($this->companyAliasKeys["{$companyId}|{$normalizedAlias}"]);
    }

    public function rememberCompanyAlias(int $companyId, string $normalizedAlias): void
    {
        $this->companyAliasKeys["{$companyId}|{$normalizedAlias}"] = true;
    }

    public function hasDrugAlias(int $drugId, string $normalizedAlias): bool
    {
        return isset($this->drugAliasKeys["{$drugId}|{$normalizedAlias}"]);
    }

    public function rememberDrugAlias(int $drugId, string $normalizedAlias): void
    {
        $this->drugAliasKeys["{$drugId}|{$normalizedAlias}"] = true;
    }

    protected function tenderKey(
        string $tenderNumber,
        int $countryId,
        ?int $year,
        ?string $version,
    ): string {
        return implode('|', [
            $tenderNumber,
            $countryId,
            $year ?? '',
            $version ?? '',
        ]);
    }
}
