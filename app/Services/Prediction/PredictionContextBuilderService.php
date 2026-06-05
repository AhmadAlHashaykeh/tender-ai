<?php

namespace App\Services\Prediction;

use App\Enums\PredictionFallbackLevel;
use App\Models\BidRecord;
use App\Models\Company;
use App\Models\OutlierFlag;
use App\Models\Prediction;
use App\Models\PredictionContextSnapshot;
use App\Models\PricingStatistic;
use App\Services\Statistics\OutlierDetectionService;

class PredictionContextBuilderService
{
    public function __construct(
        protected TenderRecommendationContextService $tenderContext,
    ) {}

    /**
     * @param  array<string, mixed>  $calculationResult
     * @return array{snapshot: array<string, mixed>, snapshot_model: PredictionContextSnapshot}
     */
    public function buildAndStore(
        Prediction $prediction,
        PricingStatistic $statistic,
        PredictionFallbackLevel $fallbackLevel,
        int $countryId,
        array $calculationResult,
        ?float $quantity,
        ?int $tenderId = null,
        ?string $tenderGroupKey = null,
    ): array {
        $tenderId = $tenderId ?? $prediction->tender_id;
        $tenderGroupKey = $tenderGroupKey
            ?? ($calculationResult['calculation_details']['tender_group_key'] ?? null);
        $tenderSnapshot = $this->tenderContext->buildTenderSnapshot($tenderId);
        $tenderGroupSnapshot = $this->tenderContext->buildTenderGroupSnapshot($tenderGroupKey);
        $tenderSpecificAwards = filled($tenderGroupKey)
            ? $this->tenderContext->tenderGroupAwards($tenderGroupKey, $prediction->standardized_drug_id)
            : ($tenderId !== null
                ? $this->tenderContext->tenderSpecificAwards($tenderId, $prediction->standardized_drug_id)
                : []);
        $tenderStatsHook = $tenderId !== null
            ? $this->tenderContext->tenderStatsAvailability($tenderId, $prediction->standardized_drug_id)
            : null;

        $snapshot = [
            'tender_context' => $tenderSnapshot,
            'tender_group_context' => $tenderGroupSnapshot,
            'tender_group_key' => $tenderGroupKey,
            'tender_specific_awards' => $tenderSpecificAwards,
            'tender_stats_availability' => $tenderStatsHook,
            'selected_stats_row' => $this->statisticSummary($statistic),
            'fallback_level' => $fallbackLevel->value,
            'recent_winning_bids' => $this->recentWinningBidsSummary(
                $prediction->standardized_drug_id,
                $countryId,
            ),
            'outlier_summary' => $this->outlierSummary(
                $prediction->standardized_drug_id,
                $countryId,
            ),
            'competition_summary' => [
                'level' => $calculationResult['competition']['level'] ?? null,
                'score' => $calculationResult['competition']['score'] ?? null,
                'distinct_winners' => $statistic->distinct_winners_count,
                'top_winner_company_id' => $statistic->top_winner_company_id,
                'top_winner_name' => $statistic->top_winner_company_id
                    ? Company::query()->whereKey($statistic->top_winner_company_id)->value('name')
                    : null,
            ],
            'quantity_comparison' => null,
            'discount_applied' => [
                'discount_percentage' => $calculationResult['discount_percentage'] ?? 0,
                'market_calculated_price' => $calculationResult['market_calculated_price'] ?? null,
                'final_recommended_price' => $calculationResult['final_recommended_price'] ?? null,
            ],
            'calculation_breakdown' => $calculationResult['calculation_details'] ?? [],
            'requested_quantity' => $quantity,
        ];

        $hash = hash('sha256', json_encode($snapshot));

        $model = PredictionContextSnapshot::query()->create([
            'prediction_id' => $prediction->id,
            'snapshot_hash' => $hash,
            'snapshot_data' => $snapshot,
            'stats_version' => $statistic->stats_version,
        ]);

        return [
            'snapshot' => $snapshot,
            'snapshot_model' => $model,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function statisticSummary(PricingStatistic $statistic): array
    {
        return [
            'id' => $statistic->id,
            'standardized_drug_id' => $statistic->standardized_drug_id,
            'country_id' => $statistic->country_id,
            'region_id' => $statistic->region_id,
            'award_count' => $statistic->award_count,
            'weighted_avg_unit_price' => $statistic->weighted_avg_unit_price,
            'median_unit_price' => $statistic->median_unit_price,
            'last_unit_price' => $statistic->last_unit_price,
            'avg_unit_price' => $statistic->avg_unit_price,
            'trend_direction' => $statistic->trend_direction,
            'trend_pct' => $statistic->trend_pct,
            'stats_version' => $statistic->stats_version,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function recentWinningBidsSummary(int $standardizedDrugId, int $countryId, int $limit = 5): array
    {
        return BidRecord::query()
            ->analyticsEligible()
            ->with('company:id,name')
            ->where('standardized_drug_id', $standardizedDrugId)
            ->where('country_id', $countryId)
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
     * @return array<string, mixed>
     */
    protected function outlierSummary(int $standardizedDrugId, int $countryId): array
    {
        $bidIds = BidRecord::query()
            ->analyticsEligible()
            ->where('standardized_drug_id', $standardizedDrugId)
            ->where('country_id', $countryId)
            ->pluck('id');

        $count = OutlierFlag::query()
            ->where('entity_type', 'bid_record')
            ->whereIn('entity_id', $bidIds)
            ->where('flag_type', OutlierDetectionService::FLAG_TYPE)
            ->where('is_resolved', false)
            ->count();

        return [
            'count' => $count,
            'flag_type' => OutlierDetectionService::FLAG_TYPE,
        ];
    }
}
