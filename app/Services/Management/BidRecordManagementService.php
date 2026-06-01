<?php

namespace App\Services\Management;

use App\Models\BidRecord;
use App\Models\Company;
use App\Models\Country;
use App\Models\ImportBatch;
use App\Models\StandardizedDrug;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class BidRecordManagementService
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

    public function paginate(Request $request): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage($request);

        return $this->buildFilteredQuery($request)
            ->with([
                'tender',
                'tenderItem',
                'standardizedDrug',
                'company',
                'country',
                'currency',
                'sourceImportRow',
                'importBatch',
            ])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, int>
     */
    public function summaryStats(): array
    {
        $base = BidRecord::query();

        return [
            'total' => (clone $base)->count(),
            'analytics_ready' => (clone $base)->where('is_analytics_ready', true)->count(),
            'excluded_from_stats' => (clone $base)->where('excluded_from_stats', true)->count(),
            'countries' => (clone $base)->whereNotNull('country_id')->distinct('country_id')->count('country_id'),
            'companies' => (clone $base)->whereNotNull('company_id')->distinct('company_id')->count('company_id'),
            'drugs' => (clone $base)->whereNotNull('standardized_drug_id')->distinct('standardized_drug_id')->count('standardized_drug_id'),
            'awarded' => (clone $base)->where('bid_status', 'awarded')->count(),
        ];
    }

    /**
     * @return array{
     *     countries: Collection,
     *     years: Collection,
     *     companies: Collection,
     *     drugs: Collection,
     *     import_batches: Collection,
     *     bid_statuses: array<int, string>
     * }
     */
    public function filterOptions(): array
    {
        $countryIds = BidRecord::query()->whereNotNull('country_id')->distinct()->pluck('country_id');
        $companyIds = BidRecord::query()->whereNotNull('company_id')->distinct()->pluck('company_id');
        $drugIds = BidRecord::query()->whereNotNull('standardized_drug_id')->distinct()->pluck('standardized_drug_id');
        $batchIds = BidRecord::query()->whereNotNull('import_batch_id')->distinct()->pluck('import_batch_id');

        return [
            'countries' => Country::query()->whereIn('id', $countryIds)->orderBy('name')->get(['id', 'name']),
            'years' => BidRecord::query()
                ->whereNotNull('award_year')
                ->distinct()
                ->orderByDesc('award_year')
                ->pluck('award_year'),
            'companies' => Company::query()->whereIn('id', $companyIds)->orderBy('name')->get(['id', 'name']),
            'drugs' => StandardizedDrug::query()->whereIn('id', $drugIds)->orderBy('display_name')->get(['id', 'code', 'inn', 'display_name']),
            'import_batches' => ImportBatch::query()->whereIn('id', $batchIds)->orderByDesc('id')->get(['id', 'original_filename', 'filename', 'created_at']),
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
            'tender_number',
            'company_id',
            'standardized_drug_id',
            'bid_status',
            'analytics_ready',
            'winner',
            'excluded',
            'import_batch_id',
            'price_min',
            'price_max',
            'qty_min',
            'qty_max',
            'per_page',
        ]);
    }

    public function buildFilteredQuery(Request $request): Builder
    {
        $query = BidRecord::query();

        if ($search = trim((string) $request->input('search', ''))) {
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like) {
                $builder
                    ->whereHas('standardizedDrug', function (Builder $drugQuery) use ($like) {
                        $drugQuery->where('code', 'like', $like)
                            ->orWhere('inn', 'like', $like);
                    })
                    ->orWhereHas('sourceImportRow', function (Builder $rowQuery) use ($like) {
                        $rowQuery->where('raw_code', 'like', $like)
                            ->orWhere('raw_inn', 'like', $like)
                            ->orWhere('raw_product_name', 'like', $like)
                            ->orWhere('raw_tender_number', 'like', $like);
                    })
                    ->orWhereHas('tenderItem', function (Builder $itemQuery) use ($like) {
                        $itemQuery->where('description', 'like', $like);
                    })
                    ->orWhereHas('company', function (Builder $companyQuery) use ($like) {
                        $companyQuery->where('name', 'like', $like);
                    })
                    ->orWhereHas('tender', function (Builder $tenderQuery) use ($like) {
                        $tenderQuery->where('tender_number', 'like', $like);
                    });
            });
        }

        if ($request->filled('country_id')) {
            $query->where('country_id', (int) $request->input('country_id'));
        }

        if ($request->filled('year')) {
            $year = (int) $request->input('year');
            $query->where(function (Builder $builder) use ($year) {
                $builder->where('award_year', $year)
                    ->orWhereHas('tender', fn (Builder $tenderQuery) => $tenderQuery->where('year', $year));
            });
        }

        if ($tenderNumber = trim((string) $request->input('tender_number', ''))) {
            $like = '%'.$tenderNumber.'%';
            $query->whereHas('tender', fn (Builder $tenderQuery) => $tenderQuery->where('tender_number', 'like', $like));
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', (int) $request->input('company_id'));
        }

        if ($request->filled('standardized_drug_id')) {
            $query->where('standardized_drug_id', (int) $request->input('standardized_drug_id'));
        }

        if ($bidStatus = $request->input('bid_status')) {
            if (in_array($bidStatus, self::BID_STATUSES, true)) {
                $query->where('bid_status', $bidStatus);
            }
        }

        $analyticsReady = $request->input('analytics_ready', 'all');
        if ($analyticsReady === 'yes') {
            $query->where('is_analytics_ready', true);
        } elseif ($analyticsReady === 'no') {
            $query->where('is_analytics_ready', false);
        }

        $winner = $request->input('winner', 'all');
        if ($winner === 'winner') {
            $query->where('is_winner', true);
        } elseif ($winner === 'non_winner') {
            $query->where('is_winner', false);
        }

        $excluded = $request->input('excluded', 'all');
        if ($excluded === 'excluded') {
            $query->where('excluded_from_stats', true);
        } elseif ($excluded === 'included') {
            $query->where('excluded_from_stats', false);
        }

        if ($request->filled('import_batch_id')) {
            $query->where('import_batch_id', (int) $request->input('import_batch_id'));
        }

        if ($request->filled('price_min')) {
            $query->where('price_usd', '>=', (float) $request->input('price_min'));
        }

        if ($request->filled('price_max')) {
            $query->where('price_usd', '<=', (float) $request->input('price_max'));
        }

        if ($request->filled('qty_min')) {
            $query->where('quantity', '>=', (float) $request->input('qty_min'));
        }

        if ($request->filled('qty_max')) {
            $query->where('quantity', '<=', (float) $request->input('qty_max'));
        }

        return $query;
    }

    public function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 25);

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : 25;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateBidRecord(BidRecord $bidRecord, array $validated, ?int $editorId = null): BidRecord
    {
        $metadata = $bidRecord->metadata ?? [];
        $metadata['edited_by'] = $editorId;
        $metadata['edited_at'] = now()->toIso8601String();

        $bidRecord->fill($validated);
        $bidRecord->metadata = $metadata;
        $bidRecord->save();

        return $bidRecord->fresh();
    }

    public function toggleExclusion(BidRecord $bidRecord, ?string $exclusionReason = null): BidRecord
    {
        $bidRecord->excluded_from_stats = ! $bidRecord->excluded_from_stats;

        if ($bidRecord->excluded_from_stats) {
            $bidRecord->exclusion_reason = $exclusionReason ?: $bidRecord->exclusion_reason;
        } else {
            $bidRecord->exclusion_reason = null;
        }

        $bidRecord->save();

        return $bidRecord->fresh();
    }
}
