<?php

namespace App\Services;

use App\Models\BidRecord;
use App\Models\Company;
use App\Models\Prediction;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Services\Tender\TenderIntelligenceService;
use Illuminate\Database\Eloquent\Builder;

class GlobalSearchService
{
    public const PER_CATEGORY_LIMIT = 5;

    public function __construct(
        protected TenderIntelligenceService $tenderIntelligence,
    ) {}

    /**
     * @return array{
     *     tenders: array<int, array<string, mixed>>,
     *     drugs: array<int, array<string, mixed>>,
     *     companies: array<int, array<string, mixed>>,
     *     predictions: array<int, array<string, mixed>>
     * }
     */
    public function search(string $query, int $userId): array
    {
        $like = '%'.$this->escapeLike($query).'%';

        return [
            'tenders' => $this->searchTenders($like, $query),
            'drugs' => $this->searchDrugs($like),
            'companies' => $this->searchCompanies($like),
            'predictions' => $this->searchPredictions($like, $userId),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchTenders(string $like, string $query): array
    {
        $builder = Tender::query()
            ->with(['country'])
            ->withCount('tenderItems')
            ->where(function (Builder $q) use ($like, $query) {
                $q->where('tenders.tender_number', 'like', $like)
                    ->orWhere('tenders.title', 'like', $like);

                if (preg_match('/^\d{4}$/', $query) === 1) {
                    $q->orWhere('tenders.year', (int) $query);
                }

                $q->orWhereHas('country', fn (Builder $countryQuery) => $countryQuery->where('name', 'like', $like));
            })
            ->orderByDesc('year')
            ->orderBy('title')
            ->limit(self::PER_CATEGORY_LIMIT);

        return $builder->get()->map(function (Tender $tender) {
            $countryName = $tender->country?->name ?? 'Unknown';
            $productCount = (int) ($tender->tender_items_count ?? 0);

            return [
                'id' => $tender->id,
                'title' => $this->tenderIntelligence->displayName($tender),
                'subtitle' => sprintf(
                    '%s • %s %s',
                    $countryName,
                    number_format($productCount),
                    $productCount === 1 ? 'product' : 'products',
                ),
                'url' => route('tenders.show', $tender),
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchDrugs(string $like): array
    {
        $drugs = StandardizedDrug::query()
            ->where(function (Builder $builder) use ($like) {
                $builder
                    ->where('standardized_drugs.code', 'like', $like)
                    ->orWhere('standardized_drugs.inn', 'like', $like)
                    ->orWhere('standardized_drugs.display_name', 'like', $like)
                    ->orWhereHas('drugAliases', fn (Builder $q) => $q->where('alias_value', 'like', $like));
            })
            ->orderBy('display_name')
            ->limit(self::PER_CATEGORY_LIMIT)
            ->get(['id', 'display_name', 'inn', 'code']);

        return $drugs->map(function (StandardizedDrug $drug) {
            $name = trim((string) ($drug->display_name ?: $drug->inn ?: $drug->code));
            $price = BidRecord::query()
                ->where('standardized_drug_id', $drug->id)
                ->where('bid_status', 'awarded')
                ->whereNotNull('price_usd')
                ->orderByDesc('award_year')
                ->orderByDesc('created_at')
                ->value('price_usd');
            $subtitle = $price !== null
                ? 'Last price: '.number_format((float) $price, 2).' USD'
                : 'No awarded price on record';

            return [
                'id' => $drug->id,
                'title' => $name,
                'subtitle' => $subtitle,
                'url' => route('drugs.show', $drug),
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchCompanies(string $like): array
    {
        $companies = Company::query()
            ->where(function (Builder $builder) use ($like) {
                $builder
                    ->where('companies.name', 'like', $like)
                    ->orWhereHas('companyAliases', fn (Builder $q) => $q->where('alias_value', 'like', $like));
            })
            ->orderBy('name')
            ->limit(self::PER_CATEGORY_LIMIT)
            ->get(['id', 'name']);

        return $companies->map(function (Company $company) {
            $wins = (int) BidRecord::query()
                ->where('company_id', $company->id)
                ->where('is_winner', true)
                ->distinct()
                ->count('tender_id');

            return [
                'id' => $company->id,
                'title' => $company->name,
                'subtitle' => sprintf(
                    '%s %s won',
                    number_format($wins),
                    $wins === 1 ? 'tender' : 'tenders',
                ),
                'url' => route('companies.show', $company),
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchPredictions(string $like, int $userId): array
    {
        return Prediction::query()
            ->with(['standardizedDrug', 'tender.country', 'currency'])
            ->where('user_id', $userId)
            ->where(function (Builder $builder) use ($like) {
                $builder
                    ->where('uuid', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhere('risk_level', 'like', $like)
                    ->orWhereHas('standardizedDrug', function (Builder $drugQuery) use ($like) {
                        $drugQuery->where('display_name', 'like', $like)
                            ->orWhere('inn', 'like', $like)
                            ->orWhere('code', 'like', $like);
                    })
                    ->orWhereHas('tender', function (Builder $tenderQuery) use ($like) {
                        $tenderQuery->where('title', 'like', $like)
                            ->orWhere('tender_number', 'like', $like);
                    });
            })
            ->latest()
            ->limit(self::PER_CATEGORY_LIMIT)
            ->get()
            ->map(function (Prediction $prediction) {
                $drugName = $prediction->standardizedDrug?->display_name
                    ?? $prediction->standardizedDrug?->inn
                    ?? 'Unknown drug';
                $tenderLabel = $prediction->tender
                    ? $this->tenderIntelligence->displayName($prediction->tender)
                    : 'No tender';
                $price = $prediction->final_recommended_price ?? $prediction->recommended_price;
                $currencyCode = $prediction->currency?->code ?? 'USD';
                $priceLabel = $price !== null
                    ? number_format((float) $price, 2).' '.$currencyCode
                    : 'Price pending';

                return [
                    'id' => $prediction->uuid,
                    'title' => $drugName,
                    'subtitle' => sprintf('%s • %s • %s', $tenderLabel, ucfirst((string) $prediction->status), $priceLabel),
                    'url' => route('ai.recommendations.show', $prediction),
                ];
            })
            ->all();
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
