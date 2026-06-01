<?php

namespace App\Services\Statistics;

use App\Models\BidRecord;
use App\Models\OutlierFlag;
use Illuminate\Support\Collection;

class OutlierDetectionService
{
    public const ENTITY_TYPE = 'bid_record';

    public const FLAG_TYPE = 'iqr_price_outlier';

    public const SOURCE = 'pricing_statistics_refresh';

    public function __construct(
        protected PricingAggregationService $aggregation,
    ) {}

    /**
     * @return array{
     *     groups_processed: int,
     *     outliers_flagged: int,
     *     skipped_groups: int,
     *     resolved_duplicates: int
     * }
     */
    public function detectForDrugCountry(
        int $standardizedDrugId,
        int $countryId,
        bool $persist = true,
    ): array {
        $records = BidRecord::query()
            ->analyticsEligible()
            ->where('standardized_drug_id', $standardizedDrugId)
            ->where('country_id', $countryId)
            ->get();

        return $this->detectForRecords($records, [
            'standardized_drug_id' => $standardizedDrugId,
            'country_id' => $countryId,
        ], $persist);
    }

    /**
     * @param  Collection<int, array{standardized_drug_id: int, country_id: int}>  $groups
     * @return array{
     *     groups_processed: int,
     *     outliers_flagged: int,
     *     skipped_groups: int
     * }
     */
    public function detectForGroups(Collection $groups, bool $persist = true): array
    {
        $summary = [
            'groups_processed' => 0,
            'outliers_flagged' => 0,
            'skipped_groups' => 0,
        ];

        foreach ($groups as $group) {
            $result = $this->detectForDrugCountry(
                $group['standardized_drug_id'],
                $group['country_id'],
                $persist,
            );

            $summary['groups_processed'] += $result['groups_processed'];
            $summary['outliers_flagged'] += $result['outliers_flagged'];
            $summary['skipped_groups'] += $result['skipped_groups'];
        }

        return $summary;
    }

    /**
     * @param  Collection<int, BidRecord>  $records
     * @param  array{standardized_drug_id: int, country_id: int}  $groupContext
     * @return array{
     *     groups_processed: int,
     *     outliers_flagged: int,
     *     skipped_groups: int
     * }
     */
    protected function detectForRecords(
        Collection $records,
        array $groupContext,
        bool $persist,
    ): array {
        if ($records->count() < 4) {
            return [
                'groups_processed' => 1,
                'outliers_flagged' => 0,
                'skipped_groups' => 1,
            ];
        }

        $prices = $records->map(fn (BidRecord $r) => (float) $r->price_usd)->all();
        $bounds = $this->aggregation->iqrBounds($prices);

        if ($bounds === null) {
            return [
                'groups_processed' => 1,
                'outliers_flagged' => 0,
                'skipped_groups' => 1,
            ];
        }

        $flagged = 0;

        foreach ($records as $record) {
            $price = (float) $record->price_usd;
            if ($price >= $bounds['lower'] && $price <= $bounds['upper']) {
                continue;
            }

            if ($this->hasExistingFlag($record->id)) {
                continue;
            }

            if ($persist) {
                OutlierFlag::query()->create([
                    'entity_type' => self::ENTITY_TYPE,
                    'entity_id' => $record->id,
                    'flag_type' => self::FLAG_TYPE,
                    'severity' => 'medium',
                    'reason' => sprintf(
                        'Unit price %.4f USD is outside IQR bounds [%.4f, %.4f] for drug %d in country %d.',
                        $price,
                        $bounds['lower'],
                        $bounds['upper'],
                        $groupContext['standardized_drug_id'],
                        $groupContext['country_id'],
                    ),
                    'deviation_score' => $this->deviationScore($price, $bounds),
                    'is_resolved' => false,
                    'metadata' => [
                        'source' => self::SOURCE,
                        'group' => $groupContext,
                        'bounds' => $bounds,
                        'price_usd' => $price,
                    ],
                ]);
            }

            $flagged++;
        }

        return [
            'groups_processed' => 1,
            'outliers_flagged' => $flagged,
            'skipped_groups' => 0,
        ];
    }

    protected function hasExistingFlag(int $bidRecordId): bool
    {
        return OutlierFlag::query()
            ->where('entity_type', self::ENTITY_TYPE)
            ->where('entity_id', $bidRecordId)
            ->where('flag_type', self::FLAG_TYPE)
            ->where('is_resolved', false)
            ->where('metadata->source', self::SOURCE)
            ->exists();
    }

    /**
     * @param  array{lower: float, upper: float, q1: float, q3: float}  $bounds
     */
    protected function deviationScore(float $price, array $bounds): float
    {
        if ($price < $bounds['lower'] && $bounds['lower'] != 0.0) {
            return round((($bounds['lower'] - $price) / $bounds['lower']) * 100, 4);
        }

        if ($price > $bounds['upper'] && $bounds['upper'] != 0.0) {
            return round((($price - $bounds['upper']) / $bounds['upper']) * 100, 4);
        }

        return 0.0;
    }
}
