<?php

namespace App\Services\Import;

class ImportChunkSizeResolver
{
    /**
     * Use one chunk for small imports; otherwise use configured chunk size.
     */
    public static function forRowCount(int $totalRows, string $configKey, int $default): int
    {
        $configured = max(1, (int) config($configKey, $default));
        $singlePassMax = max(0, (int) config('import.single_job_max_rows', 500));

        if ($totalRows > 0 && $singlePassMax > 0 && $totalRows <= $singlePassMax) {
            return $totalRows;
        }

        return $configured;
    }
}
