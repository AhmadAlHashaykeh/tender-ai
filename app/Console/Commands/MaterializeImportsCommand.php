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
                            {--only-approved=true : Only auto_approved/approved rows}';

    protected $description = 'Materialize eligible import rows into domain entities (Phase 4B)';

    public function handle(ImportMaterializationService $service): int
    {
        $batchId = $this->option('batch');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');
        $onlyApproved = filter_var($this->option('only-approved'), FILTER_VALIDATE_BOOLEAN);

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

            if ($dryRun) {
                $batchSummary = $this->dryRunBatch($batch, $service, $onlyApproved, $limit);
            } else {
                $batchSummary = $service->materializeBatch($batch, $onlyApproved, $limit, true);
            }

            foreach ($summary as $key => $value) {
                $summary[$key] += $batchSummary[$key];
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            collect($summary)->map(fn ($count, $metric) => [str_replace('_', ' ', ucfirst($metric)), $count])->values()->all()
        );

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
