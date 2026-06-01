<?php

namespace App\Console\Commands;

use App\Services\Statistics\StatisticsRefreshService;
use Illuminate\Console\Command;

class RefreshPricingStatisticsCommand extends Command
{
    protected $signature = 'stats:refresh
                            {--drug= : Standardized drug ID}
                            {--country= : Country ID}
                            {--all : Refresh all drug/country groups (includes regional/global fallbacks)}
                            {--dry-run : Simulate without persisting}';

    protected $description = 'Recalculate pricing statistics from analytics-ready bid records (Phase 5)';

    public function handle(StatisticsRefreshService $service): int
    {
        $drugId = $this->option('drug') !== null ? (int) $this->option('drug') : null;
        $countryId = $this->option('country') !== null ? (int) $this->option('country') : null;
        $dryRun = (bool) $this->option('dry-run');
        $includeFallbacks = (bool) $this->option('all') || ($drugId === null && $countryId === null);

        if ($dryRun) {
            $this->warn('Dry run enabled — no database writes.');
        }

        $summary = $service->refreshSubset(
            $drugId,
            $countryId,
            ! $dryRun,
            $includeFallbacks,
        );

        $this->newLine();
        $this->info('Pricing statistics refresh complete.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Groups processed', $summary['groups_processed']],
                ['Drug × country groups', $summary['drug_country_groups']],
                ['Drug × region groups', $summary['drug_region_groups']],
                ['Drug global groups', $summary['drug_global_groups']],
                ['Pricing statistics created', $summary['pricing_statistics_created']],
                ['Pricing statistics updated', $summary['pricing_statistics_updated']],
                ['Outliers flagged', $summary['outliers_flagged']],
                ['Skipped groups', $summary['skipped_groups']],
            ],
        );

        return self::SUCCESS;
    }
}
