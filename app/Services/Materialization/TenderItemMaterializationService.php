<?php

namespace App\Services\Materialization;

use App\Models\ImportRow;
use App\Models\TenderItem;

class TenderItemMaterializationService
{
    /**
     * @return array{tender_item_id: int, created: bool}
     */
    public function resolve(
        ImportRow $row,
        int $tenderId,
        int $standardizedDrugId,
    ): array {
        $existing = TenderItem::query()
            ->where('source_import_row_id', $row->id)
            ->first();

        if ($existing !== null) {
            return ['tender_item_id' => $existing->id, 'created' => false];
        }

        $quantity = $row->normalized_data['qty'] ?? null;

        $item = TenderItem::query()->create([
            'tender_id' => $tenderId,
            'source_import_row_id' => $row->id,
            'standardized_drug_id' => $standardizedDrugId,
            'line_number' => $row->row_number,
            'quantity' => $quantity,
            'description' => $row->raw_product_name,
            'metadata' => [
                'raw_inn' => $row->raw_inn,
                'raw_code' => $row->raw_code,
                'import_batch_id' => $row->import_batch_id,
            ],
        ]);

        return ['tender_item_id' => $item->id, 'created' => true];
    }
}
