<?php

namespace App\Services\Standardization;

class FuzzyMatcherService
{
    public function similarity(string $left, string $right): float
    {
        $left = trim($left);
        $right = trim($right);

        if ($left === '' || $right === '') {
            return 0.0;
        }

        if ($left === $right) {
            return 100.0;
        }

        similar_text($left, $right, $percent);

        return round((float) $percent, 2);
    }

    /**
     * @param  array<int|string, string>  $candidates
     * @return array{key: int|string, value: string, score: float}|null
     */
    public function bestMatch(string $needle, array $candidates, float $minimumScore = 0): ?array
    {
        $best = null;

        foreach ($candidates as $key => $candidate) {
            $score = $this->similarity($needle, $candidate);

            if ($score < $minimumScore) {
                continue;
            }

            if ($best === null || $score > $best['score']) {
                $best = [
                    'key' => $key,
                    'value' => $candidate,
                    'score' => $score,
                ];
            }
        }

        return $best;
    }
}
