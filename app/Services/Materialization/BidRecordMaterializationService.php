<?php

namespace App\Services\Materialization;

use App\Models\BidRecord;
use App\Models\Country;
use App\Models\ImportRow;

class BidRecordMaterializationService
{
    /**
     * @return array{bid_record_id: int, created: bool}
     */
    public function resolve(
        ImportRow $row,
        int $tenderItemId,
        int $companyId,
        int $standardizedDrugId,
        int $tenderId,
        int $countryId,
        ?MaterializationLookupCache $cache = null,
    ): array {
        if ($cache?->isRowMaterialized($row->id) || $row->bid_record_id !== null) {
            $existingId = $row->bid_record_id
                ?? BidRecord::query()->where('source_import_row_id', $row->id)->value('id');

            if ($existingId !== null) {
                return ['bid_record_id' => (int) $existingId, 'created' => false];
            }
        }

        $existing = BidRecord::query()
            ->where('source_import_row_id', $row->id)
            ->first();

        if ($existing !== null) {
            $cache?->markRowMaterialized($row->id);

            return ['bid_record_id' => $existing->id, 'created' => false];
        }

        $country = $cache?->country($countryId) ?? Country::query()->find($countryId);
        $priceUsd = $row->normalized_data['price_usd'] ?? null;
        $awardedPrice = $row->normalized_data['awarded_price'] ?? null;
        $quantity = $row->normalized_data['qty'] ?? null;
        $tenderValue = $row->normalized_data['tender_value'] ?? null;
        $awardYear = $row->normalized_data['year'] ?? null;

        $record = BidRecord::query()->create([
            'tender_item_id' => $tenderItemId,
            'company_id' => $companyId,
            'standardized_drug_id' => $standardizedDrugId,
            'tender_id' => $tenderId,
            'country_id' => $countryId,
            'currency_id' => $country?->default_currency_id,
            'bid_status' => 'awarded',
            'is_winner' => true,
            'row_type' => 'winning_bid',
            'price_usd' => $priceUsd,
            'original_awarded_price' => $awardedPrice,
            'quantity' => $quantity,
            'tender_value' => $tenderValue,
            'award_year' => $awardYear !== null ? (int) $awardYear : null,
            'source_import_row_id' => $row->id,
            'import_batch_id' => $row->import_batch_id,
            'is_analytics_ready' => true,
            'excluded_from_stats' => false,
            'metadata' => [
                'raw_price_usd' => $row->raw_price_usd,
                'raw_awarded_price' => $row->raw_awarded_price,
            ],
        ]);

        $cache?->markRowMaterialized($row->id);

        return ['bid_record_id' => $record->id, 'created' => true];
    }
}
