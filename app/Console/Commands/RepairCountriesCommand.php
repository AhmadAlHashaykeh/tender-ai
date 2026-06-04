<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Services\Import\ImportCountryRepairService;
use Illuminate\Console\Command;

class RepairCountriesCommand extends Command
{
    protected $signature = 'imports:repair-countries
                            {--batch= : Import batch ID to repair}';

    protected $description = 'Re-run country standardization for a batch without re-uploading';

    public function handle(ImportCountryRepairService $repair): int
    {
        $batchId = $this->option('batch');

        if ($batchId === null || $batchId === '') {
            $this->error('Provide --batch=ID');

            return self::FAILURE;
        }

        $batch = ImportBatch::query()->find($batchId);

        if (! $batch) {
            $this->error('Import batch not found.');

            return self::FAILURE;
        }

        $this->info("Repairing country mapping for batch #{$batch->id}…");

        $summary = $repair->repairBatch($batch);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Rows processed', $summary['processed']],
                ['Country mapped', $summary['country_mapped']],
                ['Region only (no country)', $summary['region_only']],
                ['Still unmapped', $summary['still_unmapped']],
                ['Materialization skip cleared', $summary['skip_cleared']],
            ],
        );

        $this->newLine();
        $this->comment('Next: php artisan imports:diagnose-countries '.$batch->id);
        $this->comment('Then: php artisan imports:materialize --batch='.$batch->id.' --retry-skipped');

        return self::SUCCESS;
    }
}
