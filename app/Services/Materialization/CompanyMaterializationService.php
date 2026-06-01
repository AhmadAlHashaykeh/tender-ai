<?php

namespace App\Services\Materialization;

use App\Models\Company;
use App\Models\ImportRow;
use App\Services\Materialization\Concerns\ManagesEntityAliases;
use App\Support\Normalization\TextNormalizer;

class CompanyMaterializationService
{
    use ManagesEntityAliases;

    public function __construct(
        protected TextNormalizer $normalizer,
    ) {}

    /**
     * @return array{company_id: int, created: bool}
     */
    public function resolve(ImportRow $row, int $countryId, ?MaterializationLookupCache $cache = null): array
    {
        $std = $row->normalized_data['standardization'] ?? [];
        $companyStd = $std['company'] ?? [];
        $normalizedName = $companyStd['normalized_name'] ?? null;

        if ($normalizedName === null) {
            $normalizedName = $this->normalizer->normalizeCompanyName(
                $row->raw_company_name ?? $row->raw_winner
            );
        }

        if ($normalizedName === null) {
            throw new \RuntimeException('Company identity could not be resolved for materialization.');
        }

        $created = false;

        if ($row->company_id !== null) {
            $existing = Company::query()->find($row->company_id);
            if ($existing !== null) {
                $this->syncAliases($existing->id, $row, $normalizedName);

                return ['company_id' => $existing->id, 'created' => false];
            }
        }

        $company = $cache?->findCompanyByNormalizedName($normalizedName, $countryId)
            ?? $cache?->findCompanyByNormalizedName($normalizedName)
            ?? Company::query()
                ->where('normalized_name', $normalizedName)
                ->first();

        if ($company === null) {
            $displayName = $companyStd['canonical_name']
                ?? $row->raw_company_name
                ?? $row->raw_winner
                ?? $normalizedName;

            $company = Company::query()->create([
                'name' => $displayName,
                'normalized_name' => $normalizedName,
                'country_id' => $countryId,
                'is_active' => true,
                'source' => 'import',
                'metadata' => [
                    'source_import_row_id' => $row->id,
                    'import_batch_id' => $row->import_batch_id,
                ],
            ]);
            $created = true;
            $cache?->rememberCompany($company);
        }

        $this->syncAliases($company->id, $row, $normalizedName, $cache);

        return ['company_id' => $company->id, 'created' => $created];
    }

    protected function syncAliases(
        int $companyId,
        ImportRow $row,
        string $normalizedName,
        ?MaterializationLookupCache $cache = null,
    ): void {
        $this->upsertCompanyAlias($companyId, $row->raw_company_name, 'company_name', $cache);
        $this->upsertCompanyAlias($companyId, $row->raw_winner, 'winner', $cache);
        $this->upsertCompanyAlias($companyId, $normalizedName, 'company_name', $cache);
    }
}
