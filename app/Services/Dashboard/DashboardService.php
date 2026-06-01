<?php

namespace App\Services\Dashboard;

use App\Models\BidRecord;
use App\Models\Company;
use App\Models\Country;
use App\Models\Prediction;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summaryForUser(User $user): array
    {
        $completedPredictions = Prediction::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed');

        return [
            'total_tenders' => Tender::query()->count(),
            'total_drugs' => StandardizedDrug::query()->where('is_active', true)->count(),
            'total_companies' => Company::query()->count(),
            'total_predictions' => Prediction::query()->where('user_id', $user->id)->count(),
            'avg_confidence' => round((float) (clone $completedPredictions)->avg('confidence_score'), 1),
            'completed_predictions' => (clone $completedPredictions)->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function recentAwardedBid(): ?array
    {
        $bid = BidRecord::query()
            ->with(['standardizedDrug:id,display_name', 'country:id,name', 'company:id,name'])
            ->where('bid_status', 'awarded')
            ->whereNotNull('price_usd')
            ->orderByDesc('award_year')
            ->orderByDesc('created_at')
            ->first();

        if (! $bid) {
            return null;
        }

        return [
            'drug' => $bid->standardizedDrug?->display_name ?? '—',
            'country' => $bid->country?->name ?? '—',
            'company' => $bid->company?->name ?? '—',
            'price' => '$'.number_format((float) $bid->price_usd, 2),
            'year' => $bid->award_year,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function nextUpcomingTender(): ?array
    {
        $tender = Tender::query()
            ->with('country:id,name')
            ->where('status', 'upcoming')
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->first();

        if (! $tender) {
            return null;
        }

        return [
            'title' => $tender->title ?? $tender->tender_number ?? 'Upcoming tender',
            'country' => $tender->country?->name ?? '—',
            'days_left' => null,
            'products_count' => $tender->tenderItems()->count(),
            'year' => $tender->year,
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public function tenderVolumeByCountry(int $limit = 5): Collection
    {
        return DB::table('bid_records')
            ->join('countries', 'countries.id', '=', 'bid_records.country_id')
            ->whereNotNull('bid_records.country_id')
            ->groupBy('bid_records.country_id', 'countries.name', 'countries.code')
            ->select([
                'countries.name as country_name',
                'countries.code as country_code',
                DB::raw('COUNT(*) as bid_count'),
            ])
            ->orderByDesc('bid_count')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<int, array{country_code: string, country_name: string, drugs: array<int, array{name: string, points: array<int, array{year: int, price: float}>}>}>
     */
    public function priceTrendsByCountry(int $countryLimit = 5, int $drugLimit = 5): array
    {
        $countries = Country::query()
            ->where('is_active', true)
            ->whereIn('id', function ($query) {
                $query->select('country_id')
                    ->from('bid_records')
                    ->whereNotNull('country_id')
                    ->distinct();
            })
            ->orderBy('name')
            ->limit($countryLimit)
            ->get(['id', 'name', 'code']);

        $result = [];

        foreach ($countries as $country) {
            $drugIds = BidRecord::query()
                ->where('country_id', $country->id)
                ->where('bid_status', 'awarded')
                ->whereNotNull('price_usd')
                ->select('standardized_drug_id')
                ->groupBy('standardized_drug_id')
                ->orderByRaw('COUNT(*) DESC')
                ->limit($drugLimit)
                ->pluck('standardized_drug_id');

            $drugs = [];

            foreach ($drugIds as $drugId) {
                $drug = StandardizedDrug::query()->find($drugId, ['id', 'display_name']);
                if (! $drug) {
                    continue;
                }

                $points = BidRecord::query()
                    ->where('country_id', $country->id)
                    ->where('standardized_drug_id', $drugId)
                    ->where('bid_status', 'awarded')
                    ->whereNotNull('price_usd')
                    ->whereNotNull('award_year')
                    ->select([
                        'award_year',
                        DB::raw('AVG(price_usd) as avg_price'),
                    ])
                    ->groupBy('award_year')
                    ->orderBy('award_year')
                    ->get()
                    ->map(fn ($row) => [
                        'year' => (int) $row->award_year,
                        'price' => round((float) $row->avg_price, 2),
                    ])
                    ->values()
                    ->all();

                if (count($points) >= 2) {
                    $drugs[] = [
                        'name' => $drug->display_name,
                        'points' => $points,
                    ];
                }
            }

            if ($drugs !== []) {
                $result[] = [
                    'country_code' => $country->code ?? strtoupper(substr($country->name, 0, 3)),
                    'country_name' => $country->name,
                    'drugs' => $drugs,
                ];
            }
        }

        return $result;
    }
}
