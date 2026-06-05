<?php

namespace App\Services\Prediction;

use App\Models\BidRecord;
use App\Models\Tender;
use App\Services\Tender\TenderGroupService;

class TenderRecommendationContextService
{
    public function __construct(
        protected TenderGroupService $tenderGroupService,
    ) {}
    /**
     * Build tender metadata for context snapshots and result pages.
     *
     * @return array<string, mixed>|null
     */
    public function buildTenderSnapshot(?int $tenderId): ?array
    {
        if ($tenderId === null) {
            return null;
        }

        $tender = Tender::query()
            ->with(['country:id,name,code,region_id', 'country.region:id,name'])
            ->find($tenderId);

        if ($tender === null) {
            return null;
        }

        return [
            'tender_id' => $tender->id,
            'tender_number' => $tender->tender_number,
            'title' => $tender->title,
            'year' => $tender->year,
            'status' => $tender->status,
            'country_id' => $tender->country_id,
            'country_name' => $tender->country?->name,
            'country_code' => $tender->country?->code,
            'region_id' => $tender->country?->region_id,
            'region_name' => $tender->country?->region?->name,
        ];
    }

    /**
     * Resolve country ID from tender — tender is the source of truth for geography.
     */
    public function resolveCountryId(int $tenderId): ?int
    {
        return Tender::query()->whereKey($tenderId)->value('country_id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildTenderGroupSnapshot(?string $groupKey): ?array
    {
        if (! filled($groupKey)) {
            return null;
        }

        $group = $this->tenderGroupService->findGroup($groupKey);

        if ($group === null) {
            return null;
        }

        return [
            'group_key' => $group['group_key'],
            'display_name' => $group['display_name'],
            'country_id' => $group['country_id'],
            'country_name' => $group['country_name'],
            'region_name' => $group['region_name'],
            'tender_count' => $group['tender_count'],
            'years' => $group['years'],
            'years_label' => $group['years_label'],
            'product_count' => $group['product_count'],
            'representative_tender_id' => $group['representative_tender_id'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tenderGroupAwards(string $groupKey, int $standardizedDrugId, int $limit = 10): array
    {
        $tenderIds = $this->tenderGroupService->tenderIdsForGroup($groupKey);

        if ($tenderIds === []) {
            return [];
        }

        return BidRecord::query()
            ->analyticsEligible()
            ->with('company:id,name')
            ->whereIn('tender_id', $tenderIds)
            ->where('standardized_drug_id', $standardizedDrugId)
            ->orderByDesc('award_year')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (BidRecord $record) => [
                'id' => $record->id,
                'price_usd' => $record->price_usd,
                'quantity' => $record->quantity,
                'award_year' => $record->award_year,
                'company' => $record->company?->name,
                'tender_id' => $record->tender_id,
            ])
            ->all();
    }

    /**
     * Tender-specific historical awards when bid records exist for this tender + drug.
     * Returns empty when no tender-scoped data is available (no fabrication).
     *
     * @return list<array<string, mixed>>
     */
    public function tenderSpecificAwards(int $tenderId, int $standardizedDrugId, int $limit = 10): array
    {
        return BidRecord::query()
            ->analyticsEligible()
            ->with('company:id,name')
            ->where('tender_id', $tenderId)
            ->where('standardized_drug_id', $standardizedDrugId)
            ->orderByDesc('award_year')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (BidRecord $record) => [
                'id' => $record->id,
                'price_usd' => $record->price_usd,
                'quantity' => $record->quantity,
                'award_year' => $record->award_year,
                'company' => $record->company?->name,
            ])
            ->all();
    }

    /**
     * Architecture hook: tender-level pricing statistics are not yet materialized.
     * Callers can use this to detect when tender-scoped intelligence becomes available.
     *
     * @return array{available: bool, award_count: int, message: string}
     */
    public function tenderStatsAvailability(int $tenderId, int $standardizedDrugId): array
    {
        $awardCount = BidRecord::query()
            ->analyticsEligible()
            ->where('tender_id', $tenderId)
            ->where('standardized_drug_id', $standardizedDrugId)
            ->count();

        return [
            'available' => false,
            'award_count' => $awardCount,
            'message' => $awardCount > 0
                ? 'Tender-specific awards exist but dedicated tender-level statistics are not yet enabled.'
                : 'No tender-specific award history available for this product.',
        ];
    }
}
