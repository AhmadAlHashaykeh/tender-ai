<?php

namespace App\Services\Drug;

use App\Models\BidRecord;
use App\Models\Company;
use App\Models\Country;
use App\Models\PricingStatistic;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DrugIntelligenceService
{
    public const BID_STATUSES = [
        'awarded',
        'lost',
        'participated',
        'disqualified',
        'cancelled',
        'unknown',
    ];

    public const PER_PAGE_OPTIONS = [25, 50, 100];

    private const RECENT_ACTIVITY_YEARS = 2;

    public function paginateIndex(Request $request): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage($request);

        return $this->buildIndexQuery($request)
            ->orderBy('display_name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, int|float|null>
     */
    public function indexSummaryStats(Request $request): array
    {
        $drugQuery = $this->buildIndexQuery($request);
        $drugIds = (clone $drugQuery)->pluck('standardized_drugs.id');

        $bidBase = BidRecord::query()->whereIn('standardized_drug_id', $drugIds);
        $drugsWithBids = (clone $drugQuery)->whereHas('bidRecords')->count();
        $drugsWithPricingStats = (clone $drugQuery)->whereHas('pricingStatistics')->count();

        return [
            'total_drugs' => $drugIds->count(),
            'drugs_with_bid_records' => $drugsWithBids,
            'drugs_with_pricing_stats' => $drugsWithPricingStats,
            'total_bid_records' => (clone $bidBase)->count(),
            'countries_count' => (clone $bidBase)->whereNotNull('country_id')->distinct('country_id')->count('country_id'),
            'companies_count' => (clone $bidBase)->whereNotNull('company_id')->distinct('company_id')->count('company_id'),
        ];
    }

    /**
     * @return array{
     *     countries: Collection,
     *     companies: Collection,
     *     years: Collection,
     *     bid_statuses: array<int, string>
     * }
     */
    public function filterOptions(): array
    {
        $drugIds = StandardizedDrug::query()->pluck('id');
        $bidQuery = BidRecord::query()->whereIn('standardized_drug_id', $drugIds);

        $countryIds = (clone $bidQuery)->whereNotNull('country_id')->distinct()->pluck('country_id');
        $companyIds = (clone $bidQuery)->whereNotNull('company_id')->distinct()->pluck('company_id');

        return [
            'countries' => Country::query()->whereIn('id', $countryIds)->orderBy('name')->get(['id', 'name']),
            'companies' => Company::query()->whereIn('id', $companyIds)->orderBy('name')->get(['id', 'name']),
            'years' => (clone $bidQuery)->whereNotNull('award_year')->distinct()->orderByDesc('award_year')->pluck('award_year'),
            'bid_statuses' => self::BID_STATUSES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function activeFilters(Request $request): array
    {
        return $request->only([
            'search',
            'country_id',
            'company_id',
            'tender_number',
            'year',
            'bid_status',
            'has_pricing_statistics',
            'price_usd_min',
            'price_usd_max',
            'per_page',
        ]);
    }

    public function buildIndexQuery(Request $request): Builder
    {
        $query = StandardizedDrug::query()
            ->select('standardized_drugs.*')
            ->withCount([
                'bidRecords',
                'bidRecords as awarded_count' => fn (Builder $q) => $q->where('bid_status', 'awarded'),
            ])
            ->addSelect([
                'countries_count' => $this->distinctBidSubquery('country_id'),
                'companies_count' => $this->distinctBidSubquery('company_id'),
                'last_activity_at' => BidRecord::query()
                    ->selectRaw('MAX(COALESCE(award_year, 0))')
                    ->whereColumn('standardized_drug_id', 'standardized_drugs.id')
                    ->toBase(),
                'has_pricing_stats' => PricingStatistic::query()
                    ->selectRaw('COUNT(*) > 0')
                    ->whereColumn('standardized_drug_id', 'standardized_drugs.id')
                    ->toBase(),
            ]);

        $this->applyIndexFilters($query, $request);

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function profileKpis(StandardizedDrug $drug): array
    {
        $base = $drug->bidRecords();

        $bidRecordsCount = (clone $base)->count();
        $awardedCount = (clone $base)->where('bid_status', 'awarded')->count();
        $priceBase = (clone $base)->whereNotNull('price_usd');
        $awardedPriceBase = (clone $base)->where('bid_status', 'awarded')->whereNotNull('price_usd');

        $globalStat = $drug->pricingStatistics()
            ->whereNull('country_id')
            ->whereNull('region_id')
            ->orderByDesc('calculated_at')
            ->first();

        $latestPrice = (clone $awardedPriceBase)
            ->orderByDesc('award_year')
            ->orderByDesc('created_at')
            ->value('price_usd');

        $lastActivityYearRaw = (clone $base)->max('award_year');
        $lastActivityYear = $lastActivityYearRaw !== null ? (int) $lastActivityYearRaw : null;

        return [
            'bid_records_count' => $bidRecordsCount,
            'awarded_count' => $awardedCount,
            'countries_count' => (clone $base)->whereNotNull('country_id')->distinct('country_id')->count('country_id'),
            'companies_count' => (clone $base)->whereNotNull('company_id')->distinct('company_id')->count('company_id'),
            'tenders_count' => (clone $base)->whereNotNull('tender_id')->distinct('tender_id')->count('tender_id'),
            'avg_price_usd' => (float) ($awardedPriceBase->avg('price_usd') ?? 0),
            'median_price_usd' => $globalStat?->median_unit_price,
            'min_price_usd' => $priceBase->min('price_usd'),
            'max_price_usd' => $priceBase->max('price_usd'),
            'latest_price_usd' => $latestPrice,
            'trend_direction' => $globalStat?->trend_direction,
            'trend_pct' => $globalStat?->trend_pct,
            'last_activity_year' => $lastActivityYear,
            'activity_status' => $this->resolveActivityStatus($bidRecordsCount, $lastActivityYear),
            'has_pricing_statistics' => $drug->pricingStatistics()->exists(),
        ];
    }

    public function resolveActivityStatus(int $bidRecordsCount, ?int $lastActivityYear): string
    {
        if ($bidRecordsCount === 0) {
            return 'unknown';
        }

        if ($lastActivityYear === null) {
            return 'unknown';
        }

        $recentThreshold = (int) now()->format('Y') - self::RECENT_ACTIVITY_YEARS;

        return $lastActivityYear >= $recentThreshold ? 'active' : 'inactive';
    }

    /**
     * @return Collection<int, PricingStatistic>
     */
    public function pricingStatisticsSection(StandardizedDrug $drug): Collection
    {
        return $drug->pricingStatistics()
            ->with(['country', 'region', 'topWinnerCompany'])
            ->orderByRaw('CASE WHEN country_id IS NULL AND region_id IS NULL THEN 0 WHEN country_id IS NOT NULL THEN 1 ELSE 2 END')
            ->orderBy('country_id')
            ->orderByDesc('calculated_at')
            ->get();
    }

    public function pricingScopeLabel(PricingStatistic $stat): string
    {
        if ($stat->country_id && $stat->country) {
            return $stat->country->name;
        }

        if ($stat->region_id && $stat->region) {
            return $stat->region->name ?? 'Region #'.$stat->region_id;
        }

        return 'Global';
    }

    public function paginateBidHistory(StandardizedDrug $drug, Request $request): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage($request);

        return $drug->bidRecords()
            ->with([
                'tender.country',
                'company',
                'country',
                'importBatch',
            ])
            ->orderByDesc('award_year')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'history_page')
            ->withQueryString();
    }

    /**
     * @return Collection<int, object>
     */
    public function companySummary(StandardizedDrug $drug): Collection
    {
        return DB::table('bid_records')
            ->join('companies', 'companies.id', '=', 'bid_records.company_id')
            ->where('bid_records.standardized_drug_id', $drug->id)
            ->whereNotNull('bid_records.company_id')
            ->groupBy('bid_records.company_id', 'companies.name')
            ->select([
                'bid_records.company_id',
                'companies.name as company_name',
                DB::raw('COUNT(*) as records_count'),
                DB::raw("SUM(CASE WHEN bid_records.bid_status = 'awarded' THEN 1 ELSE 0 END) as awarded_count"),
                DB::raw("AVG(CASE WHEN bid_records.bid_status = 'awarded' AND bid_records.price_usd IS NOT NULL THEN bid_records.price_usd END) as avg_price_usd"),
                DB::raw("SUM(CASE WHEN bid_records.bid_status = 'awarded' THEN COALESCE(bid_records.tender_value, 0) ELSE 0 END) as total_awarded_value"),
            ])
            ->orderByDesc('records_count')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function countrySummary(StandardizedDrug $drug): Collection
    {
        return DB::table('bid_records')
            ->join('countries', 'countries.id', '=', 'bid_records.country_id')
            ->where('bid_records.standardized_drug_id', $drug->id)
            ->whereNotNull('bid_records.country_id')
            ->groupBy('bid_records.country_id', 'countries.name')
            ->select([
                'bid_records.country_id',
                'countries.name as country_name',
                DB::raw('COUNT(*) as records_count'),
                DB::raw("SUM(CASE WHEN bid_records.bid_status = 'awarded' THEN 1 ELSE 0 END) as awarded_count"),
                DB::raw("AVG(CASE WHEN bid_records.bid_status = 'awarded' AND bid_records.price_usd IS NOT NULL THEN bid_records.price_usd END) as avg_price_usd"),
                DB::raw("MIN(CASE WHEN bid_records.price_usd IS NOT NULL THEN bid_records.price_usd END) as min_price_usd"),
                DB::raw("MAX(CASE WHEN bid_records.price_usd IS NOT NULL THEN bid_records.price_usd END) as max_price_usd"),
            ])
            ->orderByDesc('records_count')
            ->get();
    }

    public function formatMoney(?float $amount): string
    {
        if ($amount === null || $amount <= 0) {
            return '—';
        }

        if ($amount >= 1_000_000) {
            return '$'.number_format($amount / 1_000_000, 1).'M';
        }

        if ($amount >= 1_000) {
            return '$'.number_format($amount / 1_000, 1).'K';
        }

        return '$'.number_format($amount, 0);
    }

    public function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 25);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 25;
    }

    private function applyIndexFilters(Builder $query, Request $request): void
    {
        if ($search = trim((string) $request->input('search', ''))) {
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like) {
                $builder
                    ->where('standardized_drugs.code', 'like', $like)
                    ->orWhere('standardized_drugs.inn', 'like', $like)
                    ->orWhere('standardized_drugs.display_name', 'like', $like)
                    ->orWhereHas('drugAliases', fn (Builder $q) => $q->where('alias_value', 'like', $like));
            });
        }

        if ($request->filled('country_id')) {
            $countryId = (int) $request->input('country_id');
            $query->whereHas('bidRecords', fn (Builder $q) => $q->where('country_id', $countryId));
        }

        if ($request->filled('company_id')) {
            $query->whereHas(
                'bidRecords',
                fn (Builder $q) => $q->where('company_id', (int) $request->input('company_id')),
            );
        }

        if ($tenderNumber = trim((string) $request->input('tender_number', ''))) {
            $like = '%'.$tenderNumber.'%';
            $query->whereHas('bidRecords.tender', fn (Builder $q) => $q->where('tender_number', 'like', $like));
        }

        if ($request->filled('year')) {
            $year = (int) $request->input('year');
            $query->whereHas('bidRecords', function (Builder $q) use ($year) {
                $q->where('award_year', $year)
                    ->orWhereHas('tender', fn (Builder $t) => $t->where('year', $year));
            });
        }

        if ($bidStatus = $request->input('bid_status')) {
            if (in_array($bidStatus, self::BID_STATUSES, true)) {
                $query->whereHas('bidRecords', fn (Builder $q) => $q->where('bid_status', $bidStatus));
            }
        }

        $hasPricing = $request->input('has_pricing_statistics', 'all');
        if ($hasPricing === 'yes') {
            $query->whereHas('pricingStatistics');
        } elseif ($hasPricing === 'no') {
            $query->whereDoesntHave('pricingStatistics');
        }

        if ($request->filled('price_usd_min')) {
            $min = (float) $request->input('price_usd_min');
            $query->whereHas('bidRecords', fn (Builder $q) => $q->where('price_usd', '>=', $min));
        }

        if ($request->filled('price_usd_max')) {
            $max = (float) $request->input('price_usd_max');
            $query->whereHas('bidRecords', fn (Builder $q) => $q->where('price_usd', '<=', $max));
        }
    }

    private function distinctBidSubquery(string $column): \Illuminate\Database\Query\Builder
    {
        return BidRecord::query()
            ->selectRaw("COUNT(DISTINCT {$column})")
            ->whereColumn('standardized_drug_id', 'standardized_drugs.id')
            ->whereNotNull($column)
            ->toBase();
    }
}
