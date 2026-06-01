<?php

namespace App\Console\Commands;

use App\Services\Dev\TenderAiTestDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SeedTestDataCommand extends Command
{
    protected $signature = 'tenderai:seed-test-data
                            {--fresh-domain : Clear import/domain/prediction tables before seeding}
                            {--run-pipeline : Run standardize, materialize, and stats:refresh after seeding}
                            {--seed-references : Create standardized drug/company references for auto-approval}';

    protected $description = 'Reset local TenderAI domain data and seed the controlled 6-row test import dataset';

    public function handle(TenderAiTestDataService $service): int
    {
        if (! app()->environment(['local', 'testing'])) {
            if (! $this->confirm('This command is intended for local development. Continue anyway?')) {
                $this->warn('Aborted.');

                return self::FAILURE;
            }
        }

        if ($this->option('fresh-domain')) {
            $this->info('Clearing domain/import/prediction data…');
            $service->clearDomainData();
            $this->line('  Domain tables cleared (users, settings, countries, regions, currencies preserved).');
        }

        if ($this->option('seed-references') || $this->option('fresh-domain')) {
            $this->info('Seeding reference drugs and companies for auto-approval…');
            $service->seedReferenceEntities();
        }

        $this->info('Seeding controlled import batch (6 rows)…');
        $batch = $service->seedImportBatchAndRows();
        $meta = $batch->metadata ?? [];

        $this->table(
            ['Batch field', 'Value'],
            [
                ['ID', (string) $batch->id],
                ['UUID', $batch->uuid],
                ['row_count', (string) $batch->row_count],
                ['metadata.total_rows', (string) ($meta['total_rows'] ?? '—')],
                ['metadata.valid_rows', (string) ($meta['valid_rows'] ?? '—')],
                ['metadata.invalid_rows', (string) ($meta['invalid_rows'] ?? '—')],
                ['status', $batch->status],
            ]
        );

        if ($this->option('run-pipeline')) {
            $this->runPipeline($batch->id);
        } else {
            $this->newLine();
            $this->comment('Next steps:');
            $this->line('  php artisan imports:standardize --only-pending');
            $this->line('  php artisan imports:materialize');
            $this->line('  php artisan stats:refresh --all');
        }

        $this->printSummary($service);

        return self::SUCCESS;
    }

    protected function runPipeline(int $batchId): void
    {
        $this->newLine();
        $this->info('Running standardization (pending rows)…');
        Artisan::call('imports:standardize', ['--only-pending' => true]);
        $this->line(trim(Artisan::output()));

        $this->info('Running materialization…');
        Artisan::call('imports:materialize', ['--batch' => $batchId]);
        $this->line(trim(Artisan::output()));

        $this->info('Refreshing pricing statistics…');
        Artisan::call('stats:refresh', ['--all' => true]);
        $this->line(trim(Artisan::output()));
    }

    protected function printSummary(TenderAiTestDataService $service): void
    {
        $summary = $service->countsSummary();

        $this->newLine();
        $this->info('Counts summary');
        $this->table(
            ['Entity', 'Count'],
            collect($summary)
                ->only([
                    'import_batches',
                    'import_rows',
                    'standardized_drugs',
                    'companies',
                    'tenders',
                    'tender_items',
                    'bid_records',
                    'pricing_statistics',
                    'predictions',
                ])
                ->map(fn ($count, $key) => [str_replace('_', ' ', $key), $count])
                ->values()
                ->all()
        );

        $this->info('Standardized drugs');
        foreach ($summary['standardized_drug_names'] as $line) {
            $this->line('  '.$line);
        }

        $this->info('Companies');
        foreach ($summary['company_names'] as $name) {
            $this->line('  '.$name);
        }
    }
}
