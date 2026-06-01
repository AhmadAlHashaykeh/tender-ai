<?php

namespace App\Services\Statistics;

use App\Enums\TrendDirection;
use App\Models\BidRecord;
use Illuminate\Support\Collection;

class PricingAggregationService
{
    /**
     * @param  array<int|float|string>  $prices
     */
    public function average(array $prices): ?float
    {
        if ($prices === []) {
            return null;
        }

        return array_sum($prices) / count($prices);
    }

    /**
     * @param  array<int|float|string>  $prices
     */
    public function median(array $prices): ?float
    {
        if ($prices === []) {
            return null;
        }

        $sorted = array_map('floatval', $prices);
        sort($sorted, SORT_NUMERIC);
        $count = count($sorted);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $sorted[$middle];
        }

        return ($sorted[$middle - 1] + $sorted[$middle]) / 2;
    }

    /**
     * Population standard deviation.
     *
     * @param  array<int|float|string>  $prices
     */
    public function populationStandardDeviation(array $prices): ?float
    {
        $count = count($prices);
        if ($count === 0) {
            return null;
        }

        $mean = $this->average($prices);
        $sumSquaredDiff = 0.0;
        foreach ($prices as $price) {
            $diff = (float) $price - $mean;
            $sumSquaredDiff += $diff * $diff;
        }

        return sqrt($sumSquaredDiff / $count);
    }

    /**
     * @param  Collection<int, BidRecord>  $records
     */
    public function weightedAverageUnitPrice(Collection $records): ?float
    {
        if ($records->isEmpty()) {
            return null;
        }

        $maxYear = $records->max(fn (BidRecord $r) => $r->award_year);
        $weightedSum = 0.0;
        $totalWeight = 0.0;

        foreach ($records as $record) {
            $price = (float) $record->price_usd;
            $weight = $this->yearWeight($record->award_year, $maxYear);
            $weightedSum += $price * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0) {
            return null;
        }

        return $weightedSum / $totalWeight;
    }

    public function yearWeight(?int $year, mixed $maxYear): int
    {
        if ($year === null || $maxYear === null) {
            return 1;
        }

        $maxYear = (int) $maxYear;
        $diff = $maxYear - $year;

        return match (true) {
            $diff <= 0 => 3,
            $diff === 1 => 2,
            default => 1,
        };
    }

    /**
     * @param  Collection<int, BidRecord>  $records
     * @return array<int, float> year => median price
     */
    public function yearlyMedianPrices(Collection $records): array
    {
        $byYear = [];

        foreach ($records as $record) {
            $year = $record->award_year;
            if ($year === null) {
                continue;
            }
            $byYear[$year][] = (float) $record->price_usd;
        }

        ksort($byYear);
        $medians = [];
        foreach ($byYear as $year => $prices) {
            $median = $this->median($prices);
            if ($median !== null) {
                $medians[$year] = $median;
            }
        }

        return $medians;
    }

    /**
     * @param  array<int, float>  $yearlyMedians
     * @return array{direction: TrendDirection, pct: ?float}
     */
    public function trendFromYearlyMedians(array $yearlyMedians): array
    {
        if (count($yearlyMedians) < 2) {
            return [
                'direction' => TrendDirection::Unknown,
                'pct' => null,
            ];
        }

        $years = array_keys($yearlyMedians);
        sort($years, SORT_NUMERIC);
        $earliestMedian = $yearlyMedians[$years[0]];
        $latestMedian = $yearlyMedians[$years[array_key_last($years)]];

        if ($earliestMedian <= 0) {
            return [
                'direction' => TrendDirection::Unknown,
                'pct' => null,
            ];
        }

        $pct = (($latestMedian - $earliestMedian) / $earliestMedian) * 100;

        $direction = TrendDirection::Stable;
        if ($pct > 5) {
            $direction = TrendDirection::Rising;
        } elseif ($pct < -5) {
            $direction = TrendDirection::Falling;
        }

        return [
            'direction' => $direction,
            'pct' => round($pct, 4),
        ];
    }

    /**
     * @param  Collection<int, BidRecord>  $records
     */
    public function resolveTopWinnerCompanyId(Collection $records): ?int
    {
        $counts = $records
            ->filter(fn (BidRecord $r) => $r->company_id !== null)
            ->groupBy('company_id')
            ->map(fn (Collection $group) => $group->count());

        if ($counts->isEmpty()) {
            return null;
        }

        return (int) $counts->sortDesc()->keys()->first();
    }

    /**
     * @param  Collection<int, BidRecord>  $records
     */
    public function distinctWinnersCount(Collection $records): int
    {
        return $records
            ->pluck('company_id')
            ->filter()
            ->unique()
            ->count();
    }

    /**
     * @param  Collection<int, BidRecord>  $records
     */
    public function resolveLastAwardedRecord(Collection $records): ?BidRecord
    {
        return $records
            ->sortBy([
                fn (BidRecord $a, BidRecord $b) => ($b->award_year ?? 0) <=> ($a->award_year ?? 0),
                fn (BidRecord $a, BidRecord $b) => $b->id <=> $a->id,
            ])
            ->first();
    }

    public function awardDateFromRecord(BidRecord $record): ?string
    {
        if ($record->award_year !== null) {
            return sprintf('%d-12-31', $record->award_year);
        }

        return $record->created_at?->toDateString();
    }

    /**
     * @param  array<int|float|string>  $values
     */
    public function quartile(array $values, float $percentile): ?float
    {
        if ($values === []) {
            return null;
        }

        $sorted = array_map('floatval', $values);
        sort($sorted, SORT_NUMERIC);
        $index = ($percentile / 100) * (count($sorted) - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);

        if ($lower === $upper) {
            return $sorted[$lower];
        }

        $weight = $index - $lower;

        return $sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * $weight;
    }

    /**
     * @param  array<int|float|string>  $prices
     * @return array{q1: float, q3: float, iqr: float, lower: float, upper: float}|null
     */
    public function iqrBounds(array $prices): ?array
    {
        if (count($prices) < 4) {
            return null;
        }

        $q1 = $this->quartile($prices, 25);
        $q3 = $this->quartile($prices, 75);

        if ($q1 === null || $q3 === null) {
            return null;
        }

        $iqr = $q3 - $q1;

        return [
            'q1' => $q1,
            'q3' => $q3,
            'iqr' => $iqr,
            'lower' => $q1 - (1.5 * $iqr),
            'upper' => $q3 + (1.5 * $iqr),
        ];
    }
}
