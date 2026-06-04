<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Services\Materialization\ImportMaterializationService;
use Illuminate\Console\Command;

class MaterializeImportsCommand extends Command
{
    protected $signature = 'imports:materialize
                            {--batch= : Import batch ID}
                            {--limit= : Maximum rows to process}
                            {--dry-run : Simulate without persisting}
                            {--only-approved=true : Only auto_approved/approved rows}
                            {--retry-skipped : Clear prior skip markers and materialize eligible rows again}';

    protected $description = 'Materialize eligible import rows into domain entities (Phase 4B)';

    public function handle(ImportMaterializationService $service): int
    {
        $batchId = $this->option('batch');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');
        $onlyApproved = filter_var($this->option('only-approved'), FILTER_VALIDATE_BOOLEAN);
        $retrySkipped = (bool) $this->option('retry-skipped');

        if ($dryRun) {
            $this->warn('Dry run enabled — no database writes.');
        }

        $batches = $batchId
            ? ImportBatch::query()->whereKey($batchId)->get()
            : ImportBatch::query()->orderByDesc('id')->get();

        if ($batches->isEmpty()) {
            $this->error('No import batches found.');

            return self::FAILURE;
        }

        $summary = [
            'processed' => 0,
            'materialized' => 0,
            'skipped' => 0,
            'failed' => 0,
            'companies_created' => 0,
            'drugs_created' => 0,
            'tenders_created' => 0,
            'tender_items_created' => 0,
            'bid_records_created' => 0,
        ];

        foreach ($batches as $batch) {
            $this->info("Materializing batch #{$batch->id} ({$batch->original_filename})");

            if ($retrySkipped && ! $dryRun) {
                $cleared = $service->clearStaleSkipMarkers($batch);
                $this->line("Cleared {$cleared} prior skip marker(s).");
                $batch->update([
                    'metadata' => array_merge($batch->metadata ?? [], [
                        'materialization_status' => 'processing',
                        'pipeline_status' => 'preparing_materialization',
                        'pipeline_ready_at' => null,
                    ]),
                ]);
            }

            if ($dryRun) {
                $batchSummary = $this->dryRunBatch($batch, $service, $onlyApproved, $limit);
            } else {
                $batchSummary = $service->materializeBatch($batch, $onlyApproved, $limit, true, $retrySkipped);
            }

            foreach ($summary as $key => $value) {
                if ($key === 'skip_reasons') {
                    foreach ($batchSummary['skip_reasons'] ?? [] as $reason => $count) {
                        $summary['skip_reasons'][$reason] = ($summary['skip_reasons'][$reason] ?? 0) + $count;
                    }

                    continue;
                }

                $summary[$key] += $batchSummary[$key] ?? 0;
            }

            if (! $dryRun && ($batchSummary['materialized'] ?? 0) > 0) {
                app(\App\Services\Import\ImportPipelineOrchestratorService::class)
                    ->onMaterializationComplete($batch->fresh());
            }
        }

        $this->newLine();
        $tableRows = collect($summary)
            ->except(['skip_reasons'])
            ->map(fn ($count, $metric) => [str_replace('_', ' ', ucfirst($metric)), $count])
            ->values()
            ->all();
        $this->table(['Metric', 'Count'], $tableRows);

        if (! empty($summary['skip_reasons'])) {
            $this->newLine();
            $this->info('Skip reasons');
            $this->table(
                ['Reason', 'Rows'],
                collect($summary['skip_reasons'])
                    ->map(fn ($count, $reason) => [
                        \App\Services\Materialization\MaterializationEligibilityService::reasonLabels()[$reason] ?? $reason,
                        $count,
                    ])
                    ->values()
                    ->all(),
            );
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    protected function dryRunBatch(
        ImportBatch $batch,
        ImportMaterializationService $service,
        bool $onlyApproved,
        ?int $limit,
    ): array {
        $summary = [
            'processed' => 0,
            'materialized' => 0,
            'skipped' => 0,
            'failed' => 0,
            'companies_created' => 0,
            'drugs_created' => 0,
            'tenders_created' => 0,
            'tender_items_created' => 0,
            'bid_records_created' => 0,
        ];

        $rows = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->orderBy('row_number')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        foreach ($rows as $row) {
            if ($onlyApproved && ! in_array($row->standardization_status, ['auto_approved', 'approved'], true)) {
                continue;
            }

            $summary['processed']++;
            $outcome = $service->materializeRow($row, false);
            $summary[$outcome['bucket']]++;
            $summary['companies_created'] += $outcome['companies_created'];
            $summary['drugs_created'] += $outcome['drugs_created'];
            $summary['tenders_created'] += $outcome['tenders_created'];
            $summary['tender_items_created'] += $outcome['tender_items_created'];
            $summary['bid_records_created'] += $outcome['bid_records_created'];
        }

        return $summary;
    }
}
