<?php

namespace App\Jobs;

use App\Services\Statistics\StatisticsRefreshService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshPricingStatisticsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?int $standardizedDrugId = null,
        public ?int $countryId = null,
        public bool $includeFallbacks = true,
    ) {}

    public function handle(StatisticsRefreshService $service): array
    {
        return $service->refreshSubset(
            $this->standardizedDrugId,
            $this->countryId,
            true,
            $this->includeFallbacks,
        );
    }
}
