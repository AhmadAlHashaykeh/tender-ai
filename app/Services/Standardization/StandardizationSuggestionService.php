<?php

namespace App\Services\Standardization;

use App\Models\ImportRow;
use App\Models\StandardizationSuggestion;

class StandardizationSuggestionService
{
    /**
     * @param  array<string, mixed>  $suggestionData
     */
    public function storeSuggestion(
        ImportRow $row,
        string $entityType,
        array $suggestionData,
        float $confidence,
        string $source = 'rules',
        ?int $suggestedDrugId = null,
        ?int $suggestedCompanyId = null,
        ?string $rationale = null,
    ): StandardizationSuggestion {
        $inputHash = $this->buildInputHash($row->id, $entityType, $suggestionData);

        return StandardizationSuggestion::query()->firstOrCreate(
            ['input_hash' => $inputHash],
            [
                'import_row_id' => $row->id,
                'entity_type' => $entityType,
                'entity_id' => $suggestionData['entity_id'] ?? null,
                'suggested_standardized_drug_id' => $suggestedDrugId,
                'suggested_company_id' => $suggestedCompanyId,
                'confidence' => $confidence,
                'status' => 'pending',
                'source' => $source,
                'suggestion_data' => $suggestionData,
                'rationale' => $rationale,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $suggestionData
     */
    protected function buildInputHash(int $importRowId, string $entityType, array $suggestionData): string
    {
        ksort($suggestionData);

        return hash('sha256', json_encode([
            'import_row_id' => $importRowId,
            'entity_type' => $entityType,
            'data' => $suggestionData,
        ], JSON_THROW_ON_ERROR));
    }
}
