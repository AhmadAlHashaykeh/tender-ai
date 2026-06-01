<?php

namespace App\Services\Reports;

use App\Models\BidRecord;
use App\Models\Company;
use App\Models\Country;
use App\Models\Prediction;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportsService
{
    /**
     * @return array<string, int|float|null>
     */
    public function marketSummary(): array
    {
        $awardedBids = BidRecord::query()->where('bid_status', 'awarded');

        return [
            'total_tenders' => Tender::query()->count(),
            'total_drugs' => StandardizedDrug::query()->where('is_active', true)->count(),
            'total_companies' => Company::query()->count(),
            'total_awarded_bids' => (clone $awardedBids)->count(),
            'countries_active' => (clone $awardedBids)->whereNotNull('country_id')->distinct('country_id')->count('country_id'),
            'avg_awarded_price' => round((float) (clone $awardedBids)->whereNotNull('price_usd')->avg('price_usd'), 2),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public function topCompaniesByAwards(int $limit = 10): Collection
    {
        return DB::table('bid_records')
            ->join('companies', 'companies.id', '=', 'bid_records.company_id')
            ->where('bid_records.bid_status', 'awarded')
            ->whereNotNull('bid_records.company_id')
            ->groupBy('bid_records.company_id', 'companies.name')
            ->select([
                'companies.name as company_name',
                DB::raw('COUNT(*) as awards_count'),
                DB::raw('AVG(CASE WHEN bid_records.price_usd IS NOT NULL THEN bid_records.price_usd END) as avg_price_usd'),
            ])
            ->orderByDesc('awards_count')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function topDrugsByActivity(int $limit = 10): Collection
    {
        return DB::table('bid_records')
            ->join('standardized_drugs', 'standardized_drugs.id', '=', 'bid_records.standardized_drug_id')
            ->groupBy('bid_records.standardized_drug_id', 'standardized_drugs.display_name')
            ->select([
                'standardized_drugs.display_name as drug_name',
                DB::raw('COUNT(*) as bid_count'),
                DB::raw("SUM(CASE WHEN bid_records.bid_status = 'awarded' THEN 1 ELSE 0 END) as awarded_count"),
            ])
            ->orderByDesc('bid_count')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, int|float|null>
     */
    public function predictionPerformanceForUser(User $user): array
    {
        $base = Prediction::query()->where('user_id', $user->id);

        return [
            'total' => (clone $base)->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
            'failed' => (clone $base)->where('status', 'failed')->count(),
            'avg_confidence' => round((float) (clone $base)->where('status', 'completed')->avg('confidence_score'), 1),
        ];
    }

    /**
     * @return Collection<int, Prediction>
     */
    public function recentPredictionsForUser(User $user, int $limit = 10): Collection
    {
        return Prediction::query()
            ->with(['standardizedDrug:id,display_name', 'tender:id,title,tender_number'])
            ->where('user_id', $user->id)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function countryOpportunitySummary(int $limit = 10): Collection
    {
        return DB::table('bid_records')
            ->join('countries', 'countries.id', '=', 'bid_records.country_id')
            ->whereNotNull('bid_records.country_id')
            ->groupBy('bid_records.country_id', 'countries.name')
            ->select([
                'countries.name as country_name',
                DB::raw('COUNT(*) as bid_count'),
                DB::raw("SUM(CASE WHEN bid_records.bid_status = 'awarded' THEN 1 ELSE 0 END) as awarded_count"),
                DB::raw('COUNT(DISTINCT bid_records.standardized_drug_id) as drug_count'),
            ])
            ->orderByDesc('bid_count')
            ->limit($limit)
            ->get();
    }
}
