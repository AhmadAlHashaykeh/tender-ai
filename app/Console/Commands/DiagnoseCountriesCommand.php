<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Services\Import\ImportCountryDiagnosticsService;
use Illuminate\Console\Command;

class DiagnoseCountriesCommand extends Command
{
    protected $signature = 'imports:diagnose-countries
                            {batch : Import batch ID}
                            {--examples=10 : Number of example rows to show}';

    protected $description = 'Diagnose country standardization for an import batch';

    public function handle(ImportCountryDiagnosticsService $diagnostics): int
    {
        $batch = ImportBatch::query()->find($this->argument('batch'));

        if (! $batch) {
            $this->error('Import batch not found.');

            return self::FAILURE;
        }

        $report = $diagnostics->forBatch($batch, max(1, (int) $this->option('examples')));

        $this->info("Batch #{$report['batch_id']} — country diagnostics");
        $this->line("Rows with raw country: {$report['rows_with_country']}");
        $this->line("Rows with stored country_id: {$report['rows_with_country_id']}");
        $this->line("Rows missing country_id: {$report['rows_missing_country_id']}");
        $this->newLine();

        $this->info('Raw country value counts:');
        if ($report['raw_country_counts'] === []) {
            $this->line('  (none)');
        } else {
            $rows = [];
            foreach ($report['raw_country_counts'] as $raw => $count) {
                $rows[] = [$raw, $count];
            }
            $this->table(['raw_country', 'rows'], $rows);
        }

        if ($report['unmapped_raw_values'] !== []) {
            $this->newLine();
            $this->warn('Unmapped raw values (preview standardization):');
            foreach ($report['unmapped_raw_values'] as $raw => $count) {
                $this->line("  {$raw}: {$count}");
            }
        }

        if ($report['region_only_raw_values'] !== []) {
            $this->newLine();
            $this->line('Regional-only values (no country entity — repair or map to a country):');
            foreach ($report['region_only_raw_values'] as $raw => $count) {
                $this->line("  {$raw}: {$count}");
            }
        }

        if ($report['examples'] !== []) {
            $this->newLine();
            $this->info('Example rows:');
            foreach ($report['examples'] as $example) {
                $this->line(sprintf(
                    '  Row #%d (id %d): raw=%s | stored country_id=%s region_id=%s | preview country_id=%s region_id=%s | %s',
                    $example['row_number'],
                    $example['row_id'],
                    $example['raw_country'],
                    $example['stored_country_id'] ?? '—',
                    $example['stored_region_id'] ?? '—',
                    $example['preview_country_id'] ?? '—',
                    $example['preview_region_id'] ?? '—',
                    $example['match_type'] ?? '—',
                ));
                if (! empty($example['materialization_block'])) {
                    $this->line('    Materialization block: '.$example['materialization_block']);
                }
            }
        }

        return self::SUCCESS;
    }
}
