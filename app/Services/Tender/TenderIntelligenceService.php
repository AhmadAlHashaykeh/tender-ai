<?php

namespace App\Services\Tender;

use App\Models\BidRecord;
use App\Models\Company;
use App\Models\Country;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Models\TenderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TenderIntelligenceService
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
            ->with(['country'])
            ->orderByDesc('year')
            ->orderBy('tender_number')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, int|float|null>
     */
    public function indexSummaryStats(Request $request): array
    {
        $tenderQuery = $this->buildIndexQuery($request);
        $tenderIds = (clone $tenderQuery)->pluck('tenders.id');

        $bidBase = BidRecord::query()->whereIn('tender_id', $tenderIds);
        $itemsCount = TenderItem::query()->whereIn('tender_id', $tenderIds)->count();

        $totalBidRecords = (clone $bidBase)->count();
        $awardedCount = (clone $bidBase)->where('bid_status', 'awarded')->count();
        $winningCount = (clone $bidBase)->where('is_winner', true)->count();
        $totalAwardedValue = (float) (clone $bidBase)->where('bid_status', 'awarded')->sum('tender_value');

        return [
            'total_tenders' => $tenderIds->count(),
            'total_tender_items' => $itemsCount,
            'total_bid_records' => $totalBidRecords,
            'total_awarded_records' => $awardedCount,
            'total_winning_records' => $winningCount,
            'total_awarded_value' => $totalAwardedValue,
            'countries_count' => (clone $bidBase)->whereNotNull('country_id')->distinct('country_id')->count('country_id'),
            'drugs_count' => (clone $bidBase)->whereNotNull('standardized_drug_id')->distinct('standardized_drug_id')->count('standardized_drug_id'),
            'companies_count' => (clone $bidBase)->whereNotNull('company_id')->distinct('company_id')->count('company_id'),
        ];
    }

    /**
     * @return array{
     *     countries: Collection,
     *     years: Collection,
     *     versions: Collection,
     *     companies: Collection,
     *     drugs: Collection,
     *     bid_statuses: array<int, string>
     * }
     */
    public function filterOptions(): array
    {
        $tenderIds = Tender::query()->pluck('id');
        $bidQuery = BidRecord::query()->whereIn('tender_id', $tenderIds);

        $countryIds = (clone $bidQuery)->whereNotNull('country_id')->distinct()->pluck('country_id');
        $companyIds = (clone $bidQuery)->whereNotNull('company_id')->distinct()->pluck('company_id');
        $drugIds = (clone $bidQuery)->whereNotNull('standardized_drug_id')->distinct()->pluck('standardized_drug_id');

        return [
            'countries' => Country::query()->whereIn('id', $countryIds)->orWhereIn(
                'id',
                Tender::query()->whereNotNull('country_id')->distinct()->pluck('country_id'),
            )->orderBy('name')->get(['id', 'name']),
            'years' => Tender::query()->whereNotNull('year')->distinct()->orderByDesc('year')->pluck('year'),
            'versions' => Tender::query()->whereNotNull('version')->distinct()->orderBy('version')->pluck('version'),
            'companies' => Company::query()->whereIn('id', $companyIds)->orderBy('name')->get(['id', 'name']),
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
            'year',
            'version',
            'company_id',
            'standardized_drug_id',
            'bid_status',
            'winner',
            'analytics_ready',
            'price_usd_min',
            'price_usd_max',
            'tender_value_min',
            'tender_value_max',
            'per_page',
        ]);
    }

    public function buildIndexQuery(Request $request): Builder
    {
        $query = Tender::query()
            ->select('tenders.*')
            ->withCount([
                'tenderItems',
                'bidRecords',
                'bidRecords as awarded_count' => fn (Builder $q) => $q->where('bid_status', 'awarded'),
                'bidRecords as winning_count' => fn (Builder $q) => $q->where('is_winner', true),
            ])
            ->withSum(
                ['bidRecords as total_awarded_value_sum' => fn (Builder $q) => $q->where('bid_status', 'awarded')],
                'tender_value',
            )
            ->withAvg(
                ['bidRecords as avg_price_usd' => fn (Builder $q) => $q->whereNotNull('price_usd')],
                'price_usd',
            )
            ->addSelect([
                'companies_count' => $this->distinctBidSubquery('company_id'),
                'drugs_count' => $this->distinctBidSubquery('standardized_drug_id'),
                'last_activity_at' => BidRecord::query()
                    ->selectRaw('MAX(COALESCE(award_year, 0))')
                    ->whereColumn('tender_id', 'tenders.id')
                    ->toBase(),
            ]);

        $this->applyIndexFilters($query, $request);

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function profileKpis(Tender $tender): array
    {
        $base = $tender->bidRecords();

        $bidRecordsCount = (clone $base)->count();
        $awardedCount = (clone $base)->where('bid_status', 'awarded')->count();
        $itemsCount = $tender->tenderItems()->count();

        $priceBase = (clone $base)->whereNotNull('price_usd');
        $awardedPriceBase = (clone $base)->where('bid_status', 'awarded')->whereNotNull('price_usd');

        $lastActivityYearRaw = (clone $base)->max('award_year');
        $lastActivityYear = $lastActivityYearRaw !== null ? (int) $lastActivityYearRaw : $tender->year;

        return [
            'items_count' => $itemsCount,
            'bid_records_count' => $bidRecordsCount,
            'awarded_count' => $awardedCount,
            'companies_count' => (clone $base)->whereNotNull('company_id')->distinct('company_id')->count('company_id'),
            'drugs_count' => (clone $base)->whereNotNull('standardized_drug_id')->distinct('standardized_drug_id')->count('standardized_drug_id'),
            'total_awarded_value' => (float) (clone $base)->where('bid_status', 'awarded')->sum('tender_value'),
            'avg_price_usd' => (float) ($awardedPriceBase->avg('price_usd') ?? 0),
            'min_price_usd' => $priceBase->min('price_usd'),
            'max_price_usd' => $priceBase->max('price_usd'),
            'last_activity_year' => $lastActivityYear,
            'activity_status' => $this->resolveActivityStatus($bidRecordsCount, $lastActivityYear),
            'import_batch_id' => (clone $base)->whereNotNull('import_batch_id')->max('import_batch_id'),
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

    public function displayName(Tender $tender): string
    {
        return trim((string) ($tender->title ?: $tender->tender_number));
    }

    public function paginateBidHistory(Tender $tender, Request $request): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage($request);

        return $tender->bidRecords()
            ->with([
                'company',
                'standardizedDrug',
                'tenderItem',
                'country',
                'sourceImportRow',
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
    public function companySummary(Tender $tender): Collection
    {
        return DB::table('bid_records')
            ->join('companies', 'companies.id', '=', 'bid_records.company_id')
            ->where('bid_records.tender_id', $tender->id)
            ->whereNotNull('bid_records.company_id')
            ->groupBy('bid_records.company_id', 'companies.name')
            ->select([
                'bid_records.company_id',
                'companies.name as company_name',
                DB::raw('COUNT(*) as records_count'),
                DB::raw("SUM(CASE WHEN bid_records.bid_status = 'awarded' THEN 1 ELSE 0 END) as awarded_count"),
                DB::raw("SUM(CASE WHEN bid_records.bid_status = 'awarded' THEN COALESCE(bid_records.tender_value, 0) ELSE 0 END) as total_awarded_value"),
                DB::raw("AVG(CASE WHEN bid_records.bid_status = 'awarded' AND bid_records.price_usd IS NOT NULL THEN bid_records.price_usd END) as avg_price_usd"),
            ])
            ->orderByDesc('records_count')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function drugSummary(Tender $tender): Collection
    {
        return DB::table('bid_records')
            ->join('standardized_drugs', 'standardized_drugs.id', '=', 'bid_records.standardized_drug_id')
            ->where('bid_records.tender_id', $tender->id)
            ->whereNotNull('bid_records.standardized_drug_id')
            ->groupBy(
                'bid_records.standardized_drug_id',
                'standardized_drugs.display_name',
                'standardized_drugs.code',
            )
            ->select([
                'bid_records.standardized_drug_id as drug_id',
                'standardized_drugs.display_name as drug_name',
                'standardized_drugs.code as drug_code',
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
                    ->where('tenders.tender_number', 'like', $like)
                    ->orWhere('tenders.title', 'like', $like)
                    ->orWhereHas('bidRecords.company', fn (Builder $q) => $q->where('name', 'like', $like))
                    ->orWhereHas('bidRecords.standardizedDrug', function (Builder $q) use ($like) {
                        $q->where('display_name', 'like', $like)
                            ->orWhere('inn', 'like', $like)
                            ->orWhere('code', 'like', $like);
                    });
            });
        }

        if ($request->filled('country_id')) {
            $countryId = (int) $request->input('country_id');
            $query->where(function (Builder $builder) use ($countryId) {
                $builder
                    ->where('tenders.country_id', $countryId)
                    ->orWhereHas('bidRecords', fn (Builder $q) => $q->where('country_id', $countryId));
            });
        }

        if ($request->filled('year')) {
            $query->where('tenders.year', (int) $request->input('year'));
        }

        if ($request->filled('version')) {
            $query->where('tenders.version', $request->input('version'));
        }

        if ($request->filled('company_id')) {
            $query->whereHas(
                'bidRecords',
                fn (Builder $q) => $q->where('company_id', (int) $request->input('company_id')),
            );
        }

        if ($request->filled('standardized_drug_id')) {
            $query->whereHas(
                'bidRecords',
                fn (Builder $q) => $q->where('standardized_drug_id', (int) $request->input('standardized_drug_id')),
            );
        }

        if ($bidStatus = $request->input('bid_status')) {
            if (in_array($bidStatus, self::BID_STATUSES, true)) {
                $query->whereHas('bidRecords', fn (Builder $q) => $q->where('bid_status', $bidStatus));
            }
        }

        $winner = $request->input('winner', 'all');
        if ($winner === 'yes') {
            $query->whereHas('bidRecords', fn (Builder $q) => $q->where('is_winner', true));
        } elseif ($winner === 'no') {
            $query->whereHas('bidRecords', fn (Builder $q) => $q->where('is_winner', false));
        }

        $analyticsReady = $request->input('analytics_ready', 'all');
        if ($analyticsReady === 'yes') {
            $query->whereHas('bidRecords', fn (Builder $q) => $q->where('is_analytics_ready', true));
        } elseif ($analyticsReady === 'no') {
            $query->whereHas('bidRecords', fn (Builder $q) => $q->where('is_analytics_ready', false));
        }

        if ($request->filled('price_usd_min')) {
            $min = (float) $request->input('price_usd_min');
            $query->whereHas('bidRecords', fn (Builder $q) => $q->where('price_usd', '>=', $min));
        }

        if ($request->filled('price_usd_max')) {
            $max = (float) $request->input('price_usd_max');
            $query->whereHas('bidRecords', fn (Builder $q) => $q->where('price_usd', '<=', $max));
        }

        if ($request->filled('tender_value_min')) {
            $min = (float) $request->input('tender_value_min');
            $query->whereHas('bidRecords', fn (Builder $q) => $q->where('tender_value', '>=', $min));
        }

        if ($request->filled('tender_value_max')) {
            $max = (float) $request->input('tender_value_max');
            $query->whereHas('bidRecords', fn (Builder $q) => $q->where('tender_value', '<=', $max));
        }
    }

    private function distinctBidSubquery(string $column): \Illuminate\Database\Query\Builder
    {
        return BidRecord::query()
            ->selectRaw("COUNT(DISTINCT {$column})")
            ->whereColumn('tender_id', 'tenders.id')
            ->whereNotNull($column)
            ->toBase();
    }
}
