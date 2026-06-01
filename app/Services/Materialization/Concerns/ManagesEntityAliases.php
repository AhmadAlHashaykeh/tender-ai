<?php

namespace App\Services\Materialization\Concerns;

use App\Models\CompanyAlias;
use App\Models\DrugAlias;
use App\Support\Normalization\TextNormalizer;

trait ManagesEntityAliases
{
    protected function upsertCompanyAlias(
        int $companyId,
        ?string $aliasValue,
        string $aliasType,
        ?\App\Services\Materialization\MaterializationLookupCache $cache = null,
    ): void {
        if (! filled($aliasValue)) {
            return;
        }

        $normalizer = app(TextNormalizer::class);
        $normalized = $normalizer->normalizeCompanyName($aliasValue);

        if ($normalized === null) {
            return;
        }

        if ($cache?->hasCompanyAlias($companyId, $normalized)) {
            CompanyAlias::query()
                ->where('company_id', $companyId)
                ->where('normalized_alias', $normalized)
                ->first()
                ?->increment('usage_count');

            return;
        }

        $alias = CompanyAlias::query()->firstOrNew([
            'company_id' => $companyId,
            'normalized_alias' => $normalized,
        ]);

        if ($alias->exists) {
            $alias->increment('usage_count');
            $cache?->rememberCompanyAlias($companyId, $normalized);

            return;
        }

        $alias->fill([
            'alias_value' => $aliasValue,
            'alias_type' => $aliasType,
            'source' => 'import',
            'confidence' => 100,
            'usage_count' => 1,
        ]);
        $alias->save();
        $cache?->rememberCompanyAlias($companyId, $normalized);
    }

    protected function upsertDrugAlias(
        int $standardizedDrugId,
        ?string $aliasValue,
        string $aliasType,
        ?\App\Services\Materialization\MaterializationLookupCache $cache = null,
    ): void {
        if (! filled($aliasValue)) {
            return;
        }

        $normalizer = app(TextNormalizer::class);
        $normalized = match ($aliasType) {
            'code' => $normalizer->normalizeDrugCode($aliasValue),
            'inn' => $normalizer->normalizeDrugInn($aliasValue),
            default => $normalizer->normalizeDrugProductName($aliasValue),
        };

        if ($normalized === null) {
            return;
        }

        if ($cache?->hasDrugAlias($standardizedDrugId, $normalized)) {
            DrugAlias::query()
                ->where('standardized_drug_id', $standardizedDrugId)
                ->where('normalized_alias', $normalized)
                ->first()
                ?->increment('usage_count');

            return;
        }

        $alias = DrugAlias::query()->firstOrNew([
            'standardized_drug_id' => $standardizedDrugId,
            'normalized_alias' => $normalized,
        ]);

        if ($alias->exists) {
            $alias->increment('usage_count');
            $cache?->rememberDrugAlias($standardizedDrugId, $normalized);

            return;
        }

        $alias->fill([
            'alias_value' => $aliasValue,
            'alias_type' => $aliasType,
            'source' => 'import',
            'confidence' => 100,
            'usage_count' => 1,
        ]);
        $alias->save();
        $cache?->rememberDrugAlias($standardizedDrugId, $normalized);
    }
}
