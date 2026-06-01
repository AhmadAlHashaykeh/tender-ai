<?php

namespace App\Services\Standardization;

use App\Enums\StandardizationStatus;
use App\Models\Company;
use App\Models\DrugAlias;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\StandardizationLog;
use App\Models\StandardizedDrug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class StandardizationReviewService
{
    public const PER_PAGE_OPTIONS = [25, 50, 100];

    public const DEFAULT_PER_PAGE = 25;

    /**
     * @return array<string, mixed>
     */
    public function activeFilters(Request $request): array
    {
        return [
            'batch' => $request->integer('batch') ?: null,
            'status' => $request->string('status')->toString() ?: StandardizationStatus::ReviewRequired->value,
            'confidence_min' => $request->filled('confidence_min') ? (float) $request->input('confidence_min') : null,
            'confidence_max' => $request->filled('confidence_max') ? (float) $request->input('confidence_max') : null,
            'product' => $request->string('product')->toString() ?: null,
            'country' => $request->string('country')->toString() ?: null,
            'company' => $request->string('company')->toString() ?: null,
            'tender' => $request->string('tender')->toString() ?: null,
            'date_from' => $request->string('date_from')->toString() ?: null,
            'date_to' => $request->string('date_to')->toString() ?: null,
            'manual_only' => $request->boolean('manual_only'),
            'per_page' => $this->resolvePerPage($request),
        ];
    }

    public function resolvePerPage(Request $request): int
    {
        $perPage = $request->integer('per_page') ?: self::DEFAULT_PER_PAGE;

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filteredQuery(array $filters): Builder
    {
        $query = ImportRow::query()->with(['importBatch', 'standardizedDrug', 'company']);

        if (! empty($filters['batch'])) {
            $query->where('import_batch_id', $filters['batch']);
        }

        if (! empty($filters['status'])) {
            $query->where('standardization_status', $filters['status']);
        }

        if ($filters['confidence_min'] !== null) {
            $query->where('confidence_score', '>=', $filters['confidence_min']);
        }

        if ($filters['confidence_max'] !== null) {
            $query->where('confidence_score', '<=', $filters['confidence_max']);
        }

        if (! empty($filters['product'])) {
            $term = '%'.$filters['product'].'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('raw_product_name', 'like', $term)
                    ->orWhere('raw_inn', 'like', $term)
                    ->orWhere('raw_code', 'like', $term);
            });
        }

        if (! empty($filters['country'])) {
            $term = '%'.$filters['country'].'%';
            $query->where('raw_country', 'like', $term);
        }

        if (! empty($filters['company'])) {
            $term = '%'.$filters['company'].'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('raw_company_name', 'like', $term)
                    ->orWhere('raw_winner', 'like', $term);
            });
        }

        if (! empty($filters['tender'])) {
            $term = '%'.$filters['tender'].'%';
            $query->where('raw_tender_number', 'like', $term);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('updated_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('updated_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['manual_only'])) {
            $query->where('normalized_data->standardization->manual_review', true);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->orderByDesc('confidence_score')
            ->orderByDesc('updated_at')
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int|float>
     */
    public function summaryStats(array $filters): array
    {
        $base = $this->filteredQuery(array_merge($filters, ['status' => null]));

        $todayStart = now()->startOfDay();

        $approvedToday = StandardizationLog::query()
            ->where('action', 'approved_manually')
            ->where('created_at', '>=', $todayStart)
            ->when(! empty($filters['batch']), function (Builder $q) use ($filters): void {
                $q->whereHas('importRow', fn (Builder $row) => $row->where('import_batch_id', $filters['batch']));
            })
            ->count();

        $rejectedToday = StandardizationLog::query()
            ->where('action', 'rejected_manually')
            ->where('created_at', '>=', $todayStart)
            ->when(! empty($filters['batch']), function (Builder $q) use ($filters): void {
                $q->whereHas('importRow', fn (Builder $row) => $row->where('import_batch_id', $filters['batch']));
            })
            ->count();

        return [
            'total' => (clone $base)->count(),
            'high_confidence' => (clone $base)->where('confidence_score', '>=', 95)->count(),
            'medium_confidence' => (clone $base)->whereBetween('confidence_score', [80, 94.99])->count(),
            'low_confidence' => (clone $base)->where('confidence_score', '<', 80)->count(),
            'pending_review' => (clone $base)->where('standardization_status', StandardizationStatus::ReviewRequired->value)->count(),
            'approved_today' => $approvedToday,
            'rejected_today' => $rejectedToday,
            'pending' => (clone $base)->where('standardization_status', StandardizationStatus::Pending->value)->count(),
            'auto_approved' => (clone $base)->where('standardization_status', StandardizationStatus::AutoApproved->value)->count(),
            'review_required' => (clone $base)->where('standardization_status', StandardizationStatus::ReviewRequired->value)->count(),
            'rejected' => (clone $base)->where('standardization_status', StandardizationStatus::Rejected->value)->count(),
            'skipped' => (clone $base)->where('standardization_status', StandardizationStatus::Skipped->value)->count(),
        ];
    }

    /**
     * @return Collection<int, array{id: int, label: string, inn: ?string, code: ?string, type: string}>
     */
    public function searchProducts(string $query, int $limit = 15): Collection
    {
        $term = trim($query);

        if ($term === '') {
            return collect();
        }

        $like = '%'.$term.'%';

        $drugs = StandardizedDrug::query()
            ->where(function (Builder $q) use ($like): void {
                $q->where('display_name', 'like', $like)
                    ->orWhere('inn', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('product_name_normalized', 'like', $like);
            })
            ->where('is_active', true)
            ->orderBy('display_name')
            ->limit($limit)
            ->get(['id', 'display_name', 'inn', 'code']);

        $aliases = DrugAlias::query()
            ->with('standardizedDrug:id,display_name,inn,code')
            ->where('alias_value', 'like', $like)
            ->limit($limit)
            ->get();

        $results = collect();

        foreach ($drugs as $drug) {
            $results->push([
                'id' => $drug->id,
                'label' => $drug->display_name,
                'inn' => $drug->inn,
                'code' => $drug->code,
                'type' => 'product',
            ]);
        }

        foreach ($aliases as $alias) {
            if (! $alias->standardizedDrug) {
                continue;
            }

            $results->push([
                'id' => $alias->standardizedDrug->id,
                'label' => $alias->standardizedDrug->display_name,
                'inn' => $alias->standardizedDrug->inn,
                'code' => $alias->standardizedDrug->code,
                'type' => 'alias',
                'alias' => $alias->alias_value,
            ]);
        }

        return $results->unique('id')->take($limit)->values();
    }

    /**
     * @return Collection<int, array{id: int, label: string, country: ?string, type: string}>
     */
    public function searchCompanies(string $query, int $limit = 15): Collection
    {
        $term = trim($query);

        if ($term === '') {
            return collect();
        }

        $like = '%'.$term.'%';

        return Company::query()
            ->with('country:id,name')
            ->where(function (Builder $q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('normalized_name', 'like', $like)
                    ->orWhereHas('companyAliases', fn (Builder $alias) => $alias->where('alias_value', 'like', $like));
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'country_id'])
            ->map(fn (Company $company) => [
                'id' => $company->id,
                'label' => $company->name,
                'country' => $company->country?->name,
                'type' => 'company',
            ]);
    }

    public static function confidenceBand(float $score): string
    {
        if ($score >= 95) {
            return 'high';
        }

        if ($score >= 80) {
            return 'medium-high';
        }

        if ($score >= 60) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @return array{entity: string, original: ?string, suggested: ?string, confidence: float, reason: ?string}|null
     */
    public static function primaryReviewItem(ImportRow $row): ?array
    {
        $items = $row->normalized_data['standardization']['review_items'] ?? [];

        foreach ($items as $item) {
            if (($item['entity'] ?? '') === 'drug') {
                return $item;
            }
        }

        return $items[0] ?? null;
    }

    public function recentBatches(int $limit = 30): Collection
    {
        return ImportBatch::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'original_filename']);
    }

    public function countBatchReviewRequired(int $batchId): int
    {
        return ImportRow::query()
            ->where('import_batch_id', $batchId)
            ->where('standardization_status', StandardizationStatus::ReviewRequired->value)
            ->count();
    }
}
