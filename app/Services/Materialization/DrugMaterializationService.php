<?php

namespace App\Services\Materialization;

use App\Models\ImportRow;
use App\Models\StandardizedDrug;
use App\Services\Materialization\Concerns\ManagesEntityAliases;
use App\Support\Normalization\TextNormalizer;

class DrugMaterializationService
{
    use ManagesEntityAliases;

    public function __construct(
        protected TextNormalizer $normalizer,
    ) {}

    /**
     * @return array{standardized_drug_id: int, created: bool}
     */
    public function resolve(ImportRow $row, ?MaterializationLookupCache $cache = null): array
    {
        if ($row->standardized_drug_id !== null) {
            $existing = StandardizedDrug::query()->find($row->standardized_drug_id);
            if ($existing !== null) {
                $this->syncAliases($existing->id, $row, $cache);

                return ['standardized_drug_id' => $existing->id, 'created' => false];
            }
        }

        $code = $this->normalizer->normalizeDrugCode($row->raw_code);
        $inn = $this->normalizer->normalizeDrugInn($row->raw_inn);
        $productName = $this->normalizer->normalizeDrugProductName($row->raw_product_name);
        $components = $this->extractComponents($row);

        if ($code !== null) {
            $byCode = $cache?->findDrugByCode($code)
                ?? StandardizedDrug::query()
                    ->whereRaw('UPPER(code) = ?', [$code])
                    ->first();

            if ($byCode !== null) {
                $this->syncAliases($byCode->id, $row, $cache);

                return ['standardized_drug_id' => $byCode->id, 'created' => false];
            }
        }

        $std = $row->normalized_data['standardization'] ?? [];
        $suggested = $std['drug_suggestion'] ?? ($std['drug']['suggested'] ?? null);

        $payload = is_array($suggested) ? $suggested : [];
        $displayName = $payload['display_name'] ?? $row->raw_product_name ?? $row->raw_inn ?? $code;
        $productNormalized = $payload['product_name_normalized'] ?? $productName;

        $drug = StandardizedDrug::query()->create([
            'code' => $code,
            'inn' => $inn ?? $payload['inn'] ?? null,
            'display_name' => $displayName,
            'product_name_normalized' => $productNormalized,
            'dosage' => $payload['dosage'] ?? null,
            'form' => $payload['form'] ?? $components['form'] ?? null,
            'strength' => $payload['strength'] ?? $components['strength'] ?? null,
            'strength_unit' => $payload['strength_unit'] ?? $components['strength_unit'] ?? null,
            'is_active' => true,
            'source' => 'import',
        ]);

        $cache?->rememberDrug($drug);
        $this->syncAliases($drug->id, $row, $cache);

        return ['standardized_drug_id' => $drug->id, 'created' => true];
    }

    /**
     * @return array{strength: ?string, strength_unit: ?string, form: ?string}
     */
    protected function extractComponents(ImportRow $row): array
    {
        $components = $row->normalized_data['standardization']['drug']['components'] ?? null;

        if (is_array($components)) {
            return [
                'strength' => $components['strength'] ?? null,
                'strength_unit' => $components['strength_unit'] ?? null,
                'form' => $components['form'] ?? null,
            ];
        }

        return $this->normalizer->extractDrugComponents($row->raw_product_name);
    }

    protected function syncAliases(
        int $standardizedDrugId,
        ImportRow $row,
        ?MaterializationLookupCache $cache = null,
    ): void {
        $this->upsertDrugAlias($standardizedDrugId, $row->raw_product_name, 'product_name', $cache);
        $this->upsertDrugAlias($standardizedDrugId, $row->raw_code, 'code', $cache);
        $this->upsertDrugAlias($standardizedDrugId, $row->raw_inn, 'inn', $cache);
    }
}
