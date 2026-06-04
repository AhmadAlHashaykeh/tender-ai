<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Services\Materialization\MaterializationEligibilityService;
use App\Services\Materialization\MaterializationSkipDiagnosticsService;
use Illuminate\Console\Command;

class DiagnoseMaterializationCommand extends Command
{
    protected $signature = 'imports:diagnose-materialization
                            {batch : Import batch ID}
                            {--examples=5 : Number of example rows to show}';

    protected $description = 'Explain why import rows are skipped during materialization';

    public function handle(MaterializationSkipDiagnosticsService $diagnostics): int
    {
        $batch = ImportBatch::query()->find($this->argument('batch'));

        if (! $batch) {
            $this->error('Import batch not found.');

            return self::FAILURE;
        }

        $report = $diagnostics->forBatch($batch, max(1, (int) $this->option('examples')));

        $this->info("Batch #{$report['batch_id']}");
        $this->line("Rows checked: {$report['rows_checked']}");
        $this->line("Eligible for materialization: {$report['eligible_count']}");
        $this->line("Already materialized: {$report['materialized_count']}");
        $this->newLine();
        $this->info('Skip reasons (ineligible or already done):');

        if ($report['skip_reasons'] === []) {
            $this->line('  (none — all checked rows are eligible and not yet materialized)');
        } else {
            $rows = [];
            foreach ($report['skip_reasons'] as $reason => $count) {
                $rows[] = [
                    MaterializationEligibilityService::reasonLabels()[$reason] ?? $reason,
                    $count,
                ];
            }
            $this->table(['Reason', 'Rows'], $rows);
        }

        if ($report['examples'] !== []) {
            $this->newLine();
            $this->info('Example rows:');
            foreach ($report['examples'] as $example) {
                $this->line(sprintf(
                    '  Row #%d (id %d): %s — %s',
                    $example['row_number'],
                    $example['row_id'],
                    $example['skip_reason'],
                    $example['skip_details'],
                ));
                $this->line('    Original tender: '.($example['original']['raw_tender_number'] ?? '—')
                    .' | price_usd: '.($example['original']['raw_price_usd'] ?? '—')
                    .' | country: '.($example['original']['raw_country'] ?? '—'));
                $this->line('    Standardized country_id: '.($example['standardized']['country_id'] ?? '—')
                    .' | price_usd: '.($example['standardized']['price_usd'] ?? '—')
                    .' | tender: '.($example['standardized']['tender_number'] ?? '—'));
            }
        }

        return self::SUCCESS;
    }
}
