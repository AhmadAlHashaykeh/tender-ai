<?php

namespace App\Services\Tender;

use App\Models\BidRecord;
use App\Models\Country;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Models\TenderItem;
use Illuminate\Support\Collection;

class TenderGroupService
{
    public function __construct(
        protected TenderGroupKeyService $keyService,
    ) {}

    /**
     * @return Collection<int, array{
     *     group_key: string,
     *     display_name: string,
     *     country_id: ?int,
     *     country_name: ?string,
     *     region_name: ?string,
     *     tender_count: int,
     *     years: list<int>,
     *     years_label: string,
     *     product_count: int,
     *     has_upcoming: bool,
     *     representative_tender_id: ?int
     * }>
     */
    public function listGroups(): Collection
    {
        $tenders = Tender::query()
            ->with(['country:id,name,code,region_id', 'country.region:id,name'])
            ->where(function ($query): void {
                $query->whereIn('status', ['active', 'upcoming'])
                    ->orWhereHas('bidRecords', fn ($bidQuery) => $bidQuery->analyticsEligible());
            })
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->get(['id', 'tender_number', 'title', 'country_id', 'year', 'status']);

        $groups = [];

        foreach ($tenders as $tender) {
            $groupKey = $this->keyService->deriveFromTender($tender);

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'group_key' => $groupKey,
                    'display_name' => $this->keyService->displayName($groupKey),
                    'country_ids' => [],
                    'tender_ids' => [],
                    'years' => [],
                    'has_upcoming' => false,
                    'representative_tender_id' => $tender->id,
                    'country' => $tender->country,
                ];
            }

            $groups[$groupKey]['tender_ids'][] = $tender->id;
            $groups[$groupKey]['country_ids'][$tender->country_id] = ($groups[$groupKey]['country_ids'][$tender->country_id] ?? 0) + 1;

            if ($tender->year !== null) {
                $groups[$groupKey]['years'][$tender->year] = true;
            }

            if ($tender->status === 'upcoming') {
                $groups[$groupKey]['has_upcoming'] = true;
            }
        }

        return collect($groups)
            ->map(function (array $group): array {
                $tenderIds = array_values(array_unique($group['tender_ids']));
                $countryId = $this->resolvePrimaryCountryId($group['country_ids']);
                $country = $countryId !== null
                    ? Country::query()->with('region:id,name')->find($countryId)
                    : $group['country'];
                $years = array_keys($group['years']);
                sort($years);

                return [
                    'group_key' => $group['group_key'],
                    'display_name' => $group['display_name'],
                    'country_id' => $countryId,
                    'country_name' => $country?->name,
                    'region_name' => $country?->region?->name,
                    'tender_count' => count($tenderIds),
                    'years' => $years,
                    'years_label' => $this->formatYearsLabel($years),
                    'product_count' => $this->countProductsForTenders($tenderIds),
                    'has_upcoming' => $group['has_upcoming'],
                    'representative_tender_id' => $group['representative_tender_id'],
                ];
            })
            ->sortBy([
                ['has_upcoming', 'desc'],
                ['display_name', 'asc'],
            ])
            ->values();
    }

    /**
     * @return ?array<string, mixed>
     */
    public function findGroup(string $groupKey): ?array
    {
        return $this->listGroups()->firstWhere('group_key', strtoupper($groupKey));
    }

    public function groupExists(string $groupKey): bool
    {
        return $this->findGroup($groupKey) !== null;
    }

    /**
     * @return list<int>
     */
    public function tenderIdsForGroup(string $groupKey): array
    {
        $groupKey = strtoupper($groupKey);

        return Tender::query()
            ->get(['id', 'tender_number', 'title'])
            ->filter(fn (Tender $tender) => $this->keyService->deriveFromTender($tender) === $groupKey)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function resolveRepresentativeTenderId(string $groupKey): ?int
    {
        return $this->findGroup($groupKey)['representative_tender_id'] ?? null;
    }

    public function resolveCountryId(string $groupKey): ?int
    {
        return $this->findGroup($groupKey)['country_id'] ?? null;
    }

    public function isDrugInGroup(string $groupKey, int $standardizedDrugId): bool
    {
        $tenderIds = $this->tenderIdsForGroup($groupKey);

        if ($tenderIds === []) {
            return false;
        }

        $hasBidHistory = BidRecord::query()
            ->analyticsEligible()
            ->whereIn('tender_id', $tenderIds)
            ->where('standardized_drug_id', $standardizedDrugId)
            ->exists();

        if ($hasBidHistory) {
            return true;
        }

        return TenderItem::query()
            ->whereIn('tender_id', $tenderIds)
            ->where('standardized_drug_id', $standardizedDrugId)
            ->exists();
    }

    /**
     * @return Collection<int, array{
     *     drug_id: int,
     *     display_name: string,
     *     inn: ?string,
     *     code: ?string,
     *     historical_record_count: int,
     *     latest_awarded_price: ?float,
     *     country_name: ?string
     * }>
     */
    public function drugsForGroup(string $groupKey): Collection
    {
        $group = $this->findGroup($groupKey);
        $tenderIds = $this->tenderIdsForGroup($groupKey);

        if ($tenderIds === []) {
            return collect();
        }

        $bidRecords = BidRecord::query()
            ->analyticsEligible()
            ->whereIn('tender_id', $tenderIds)
            ->whereNotNull('standardized_drug_id')
            ->orderByDesc('award_year')
            ->orderByDesc('id')
            ->get(['standardized_drug_id', 'price_usd']);

        $bidAggregates = $bidRecords
            ->groupBy('standardized_drug_id')
            ->map(fn (Collection $records) => (object) [
                'record_count' => $records->count(),
                'latest_price' => $records->first()?->price_usd,
            ]);

        $itemDrugIds = TenderItem::query()
            ->whereIn('tender_id', $tenderIds)
            ->whereNotNull('standardized_drug_id')
            ->distinct()
            ->pluck('standardized_drug_id');

        $drugIds = $bidAggregates->keys()
            ->merge($itemDrugIds)
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($drugIds->isEmpty()) {
            return collect();
        }

        return StandardizedDrug::query()
            ->whereIn('id', $drugIds)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'inn', 'code'])
            ->map(function (StandardizedDrug $drug) use ($bidAggregates, $group): array {
                $aggregate = $bidAggregates->get($drug->id);

                return [
                    'drug_id' => $drug->id,
                    'display_name' => $drug->display_name,
                    'inn' => $drug->inn,
                    'code' => $drug->code,
                    'historical_record_count' => (int) ($aggregate->record_count ?? 0),
                    'latest_awarded_price' => $aggregate?->latest_price !== null
                        ? (float) $aggregate->latest_price
                        : null,
                    'country_name' => $group['country_name'] ?? null,
                ];
            });
    }

    /**
     * @param  array<int, int>  $countryCounts
     */
    protected function resolvePrimaryCountryId(array $countryCounts): ?int
    {
        if ($countryCounts === []) {
            return null;
        }

        arsort($countryCounts);

        return (int) array_key_first($countryCounts);
    }

    /**
     * @param  list<int>  $tenderIds
     */
    protected function countProductsForTenders(array $tenderIds): int
    {
        $bidDrugIds = BidRecord::query()
            ->analyticsEligible()
            ->whereIn('tender_id', $tenderIds)
            ->whereNotNull('standardized_drug_id')
            ->distinct()
            ->pluck('standardized_drug_id');

        $itemDrugIds = TenderItem::query()
            ->whereIn('tender_id', $tenderIds)
            ->whereNotNull('standardized_drug_id')
            ->distinct()
            ->pluck('standardized_drug_id');

        return $bidDrugIds->merge($itemDrugIds)->unique()->count();
    }

    /**
     * @param  list<int>  $years
     */
    protected function formatYearsLabel(array $years): string
    {
        if ($years === []) {
            return '—';
        }

        if (count($years) === 1) {
            return (string) $years[0];
        }

        return $years[0].'–'.$years[array_key_last($years)];
    }
}
