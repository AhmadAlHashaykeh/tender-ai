<?php

namespace App\Services\Company;

use App\Models\BidRecord;
use App\Models\Company;
use App\Models\Country;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompanyIntelligenceService
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

    private const PARTICIPATION_STATUSES = ['awarded', 'lost', 'participated'];

    private const RECENT_ACTIVITY_YEARS = 2;

    public function paginateIndex(Request $request): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage($request);

        return $this->buildIndexQuery($request)
            ->with(['country'])
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, int|float|string|null>
     */
    public function indexSummaryStats(Request $request): array
    {
        $companyQuery = $this->buildIndexQuery($request);
        $companyIds = (clone $companyQuery)->pluck('companies.id');

        $bidBase = BidRecord::query()->whereIn('company_id', $companyIds);

        $totalCompanies = $companyIds->count();
        $companiesWithAwarded = (clone $companyQuery)
            ->whereHas('bidRecords', fn (Builder $q) => $q->where('bid_status', 'awarded'))
            ->count();

        $totalBidRecords = (clone $bidBase)->count();
        $totalAwardedValue = (float) (clone $bidBase)->where('bid_status', 'awarded')->sum('tender_value');

        $topCountry = Country::query()
            ->select('countries.name', DB::raw('COUNT(DISTINCT companies.id) as company_count'))
            ->join('companies', 'companies.country_id', '=', 'countries.id')
            ->whereIn('companies.id', $companyIds)
            ->whereNotNull('companies.country_id')
            ->groupBy('countries.id', 'countries.name')
            ->orderByDesc('company_count')
            ->first();

        return [
            'total_companies' => $totalCompanies,
            'companies_with_awarded' => $companiesWithAwarded,
            'total_bid_records' => $totalBidRecords,
            'total_awarded_value' => $totalAwardedValue,
            'avg_bid_records_per_company' => $totalCompanies > 0
                ? round($totalBidRecords / $totalCompanies, 1)
                : 0,
            'top_country_name' => $topCountry?->name,
            'top_country_count' => $topCountry ? (int) $topCountry->company_count : null,
        ];
    }

    /**
     * @return array{
     *     countries: Collection,
     *     years: Collection,
     *     drugs: Collection,
     *     bid_statuses: array<int, string>
     * }
     */
    public function filterOptions(): array
    {
        $countryIds = BidRecord::query()->whereNotNull('country_id')->distinct()->pluck('country_id');
        $drugIds = BidRecord::query()->whereNotNull('standardized_drug_id')->distinct()->pluck('standardized_drug_id');

        return [
            'countries' => Country::query()->whereIn('id', $countryIds)->orderBy('name')->get(['id', 'name']),
            'years' => BidRecord::query()
                ->whereNotNull('award_year')
                ->distinct()
                ->orderByDesc('award_year')
                ->pluck('award_year'),
            'drugs' => StandardizedDrug::query()
                ->whereIn('id', $drugIds)
                ->orderBy('display_name')
                ->get(['id', 'code', 'inn', 'display_name']),
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
            'bid_status',
            'year',
            'tender_number',
            'standardized_drug_id',
            'awarded_value_min',
            'awarded_value_max',
            'has_awarded',
            'per_page',
        ]);
    }

    public function buildIndexQuery(Request $request): Builder
    {
        $query = Company::query()
            ->select('companies.*')
            ->withCount([
                'bidRecords',
                'bidRecords as awarded_count' => fn (Builder $q) => $q->where('bid_status', 'awarded'),
                'bidRecords as lost_count' => fn (Builder $q) => $q->where('bid_status', 'lost'),
                'bidRecords as participated_count' => fn (Builder $q) => $q->where('bid_status', 'participated'),
            ])
            ->withSum('bidRecords as total_tender_value_sum', 'tender_value')
            ->withSum(
                ['bidRecords as total_awarded_value_sum' => fn (Builder $q) => $q->where('bid_status', 'awarded')],
                'tender_value',
            )
            ->withAvg(
                ['bidRecords as avg_awarded_price_usd' => fn (Builder $q) => $q->where('bid_status', 'awarded')->whereNotNull('price_usd')],
                'price_usd',
            )
            ->addSelect([
                'countries_involved_count' => $this->distinctBidSubquery('country_id'),
                'unique_drugs_count' => $this->distinctBidSubquery('standardized_drug_id'),
                'unique_tenders_count' => $this->distinctBidSubquery('tender_id'),
                'last_activity_at' => BidRecord::query()
                    ->selectRaw('MAX(COALESCE(award_year, 0))')
                    ->whereColumn('company_id', 'companies.id')
                    ->toBase(),
                'last_drug_name' => BidRecord::query()
                    ->select('standardized_drugs.display_name')
                    ->join('standardized_drugs', 'standardized_drugs.id', '=', 'bid_records.standardized_drug_id')
                    ->whereColumn('bid_records.company_id', 'companies.id')
                    ->whereNotNull('bid_records.standardized_drug_id')
                    ->orderByDesc(DB::raw('COALESCE(bid_records.award_year, 0)'))
                    ->orderByDesc('bid_records.created_at')
                    ->limit(1)
                    ->toBase(),
            ]);

        if ($search = trim((string) $request->input('search', ''))) {
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like) {
                $builder
                    ->where('companies.name', 'like', $like)
                    ->orWhereHas('companyAliases', fn (Builder $aliasQuery) => $aliasQuery->where('alias_value', 'like', $like));
            });
        }

        if ($request->filled('country_id')) {
            $countryId = (int) $request->input('country_id');
            $query->where(function (Builder $builder) use ($countryId) {
                $builder
                    ->where('companies.country_id', $countryId)
                    ->orWhereHas('bidRecords', fn (Builder $bidQuery) => $bidQuery->where('country_id', $countryId));
            });
        }

        if ($bidStatus = $request->input('bid_status')) {
            if (in_array($bidStatus, self::BID_STATUSES, true)) {
                $query->whereHas('bidRecords', fn (Builder $bidQuery) => $bidQuery->where('bid_status', $bidStatus));
            }
        }

        if ($request->filled('year')) {
            $year = (int) $request->input('year');
            $query->whereHas('bidRecords', function (Builder $bidQuery) use ($year) {
                $bidQuery->where(function (Builder $inner) use ($year) {
                    $inner->where('award_year', $year)
                        ->orWhereHas('tender', fn (Builder $tenderQuery) => $tenderQuery->where('year', $year));
                });
            });
        }

        if ($tenderNumber = trim((string) $request->input('tender_number', ''))) {
            $like = '%'.$tenderNumber.'%';
            $query->whereHas('bidRecords.tender', fn (Builder $tenderQuery) => $tenderQuery->where('tender_number', 'like', $like));
        }

        if ($request->filled('standardized_drug_id')) {
            $query->whereHas(
                'bidRecords',
                fn (Builder $bidQuery) => $bidQuery->where('standardized_drug_id', (int) $request->input('standardized_drug_id')),
            );
        }

        $hasAwarded = $request->input('has_awarded', 'all');
        if ($hasAwarded === 'yes') {
            $query->whereHas('bidRecords', fn (Builder $bidQuery) => $bidQuery->where('bid_status', 'awarded'));
        } elseif ($hasAwarded === 'no') {
            $query->whereDoesntHave('bidRecords', fn (Builder $bidQuery) => $bidQuery->where('bid_status', 'awarded'));
        }

        if ($request->filled('awarded_value_min')) {
            $min = (float) $request->input('awarded_value_min');
            $query->whereRaw(
                '(SELECT COALESCE(SUM(tender_value), 0) FROM bid_records WHERE company_id = companies.id AND bid_status = ?) >= ?',
                ['awarded', $min],
            );
        }

        if ($request->filled('awarded_value_max')) {
            $max = (float) $request->input('awarded_value_max');
            $query->whereRaw(
                '(SELECT COALESCE(SUM(tender_value), 0) FROM bid_records WHERE company_id = companies.id AND bid_status = ?) <= ?',
                ['awarded', $max],
            );
        }

        return $query;
    }

    /**
     * @return array{label: string, percent: ?float, tone: string}
     */
    public function winRatePresentation(int $awarded, int $lost, int $participated): array
    {
        $denominator = $awarded + $lost + $participated;

        if ($denominator === 0) {
            return ['label' => '—', 'percent' => null, 'tone' => 'muted'];
        }

        if ($lost === 0 && $participated === 0 && $awarded > 0) {
            return ['label' => 'Awarded records only', 'percent' => 100.0, 'tone' => 'amber'];
        }

        $percent = round(($awarded / $denominator) * 100, 1);

        return ['label' => $percent.'%', 'percent' => $percent, 'tone' => 'primary'];
    }

    /**
     * @return array<string, mixed>
     */
    public function profileKpis(Company $company): array
    {
        $base = $company->bidRecords();

        $bidRecordsCount = (clone $base)->count();
        $awardedCount = (clone $base)->where('bid_status', 'awarded')->count();
        $lostCount = (clone $base)->where('bid_status', 'lost')->count();
        $participatedCount = (clone $base)->where('bid_status', 'participated')->count();

        $winRate = $this->winRatePresentation($awardedCount, $lostCount, $participatedCount);
        $lastActivityYearRaw = (clone $base)->max('award_year');
        $lastActivityYear = $lastActivityYearRaw !== null ? (int) $lastActivityYearRaw : null;

        return [
            'bid_records_count' => $bidRecordsCount,
            'awarded_count' => $awardedCount,
            'lost_count' => $lostCount,
            'participated_count' => $participatedCount,
            'win_rate' => $winRate,
            'total_awarded_value' => (float) (clone $base)->where('bid_status', 'awarded')->sum('tender_value'),
            'total_tender_value' => (float) (clone $base)->sum('tender_value'),
            'avg_awarded_price_usd' => (float) ((clone $base)->where('bid_status', 'awarded')->whereNotNull('price_usd')->avg('price_usd') ?? 0),
            'unique_tenders_count' => (clone $base)->whereNotNull('tender_id')->distinct('tender_id')->count('tender_id'),
            'unique_drugs_count' => (clone $base)->whereNotNull('standardized_drug_id')->distinct('standardized_drug_id')->count('standardized_drug_id'),
            'countries_involved_count' => (clone $base)->whereNotNull('country_id')->distinct('country_id')->count('country_id'),
            'first_seen_at' => (clone $base)->min('created_at'),
            'last_activity_at' => (clone $base)->max('created_at'),
            'last_activity_year' => $lastActivityYear,
            'activity_status' => $this->resolveActivityStatus($bidRecordsCount, $lastActivityYear),
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

    public function paginateBidHistory(Company $company, Request $request): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage($request);

        return $company->bidRecords()
            ->with([
                'tender.country',
                'tenderItem',
                'standardizedDrug',
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
    public function drugSummary(Company $company): Collection
    {
        return DB::table('bid_records')
            ->join('standardized_drugs', 'standardized_drugs.id', '=', 'bid_records.standardized_drug_id')
            ->where('bid_records.company_id', $company->id)
            ->whereNotNull('bid_records.standardized_drug_id')
            ->groupBy(
                'bid_records.standardized_drug_id',
                'standardized_drugs.display_name',
                'standardized_drugs.code',
                'standardized_drugs.inn',
            )
            ->select([
                'bid_records.standardized_drug_id as drug_id',
                'standardized_drugs.display_name as drug_name',
                'standardized_drugs.code as drug_code',
                DB::raw('COUNT(*) as records_count'),
                DB::raw("SUM(CASE WHEN bid_records.bid_status = 'awarded' THEN 1 ELSE 0 END) as awarded_count"),
                DB::raw("AVG(CASE WHEN bid_records.bid_status = 'awarded' AND bid_records.price_usd IS NOT NULL THEN bid_records.price_usd END) as avg_price_usd"),
                DB::raw("MIN(CASE WHEN bid_records.bid_status = 'awarded' AND bid_records.price_usd IS NOT NULL THEN bid_records.price_usd END) as min_price_usd"),
                DB::raw("MAX(CASE WHEN bid_records.bid_status = 'awarded' AND bid_records.price_usd IS NOT NULL THEN bid_records.price_usd END) as max_price_usd"),
                DB::raw('MAX(bid_records.award_year) as last_year'),
            ])
            ->orderByDesc('records_count')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function countrySummary(Company $company): Collection
    {
        return DB::table('bid_records')
            ->join('countries', 'countries.id', '=', 'bid_records.country_id')
            ->where('bid_records.company_id', $company->id)
            ->whereNotNull('bid_records.country_id')
            ->groupBy('bid_records.country_id', 'countries.name')
            ->select([
                'bid_records.country_id',
                'countries.name as country_name',
                DB::raw('COUNT(*) as records_count'),
                DB::raw("SUM(CASE WHEN bid_records.bid_status = 'awarded' THEN 1 ELSE 0 END) as awarded_count"),
                DB::raw("SUM(CASE WHEN bid_records.bid_status = 'awarded' THEN COALESCE(bid_records.tender_value, 0) ELSE 0 END) as total_awarded_value"),
                DB::raw('COUNT(DISTINCT bid_records.tender_id) as unique_tenders_count'),
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

    private function distinctBidSubquery(string $column): \Illuminate\Database\Query\Builder
    {
        return BidRecord::query()
            ->selectRaw("COUNT(DISTINCT {$column})")
            ->whereColumn('company_id', 'companies.id')
            ->whereNotNull($column)
            ->toBase();
    }
}
