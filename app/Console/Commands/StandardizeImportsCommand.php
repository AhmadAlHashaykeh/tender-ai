<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Services\Standardization\ImportRowStandardizationService;
use Illuminate\Console\Command;

class StandardizeImportsCommand extends Command
{
    protected $signature = 'imports:standardize
                            {--batch= : Import batch ID}
                            {--limit= : Maximum rows to process}
                            {--only-pending : Only process rows with pending standardization status}
                            {--dry-run : Run without persisting changes}';

    protected $description = 'Rule-based standardization for import rows (Phase 4A)';

    public function handle(ImportRowStandardizationService $service): int
    {
        $batchId = $this->option('batch');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $onlyPending = (bool) $this->option('only-pending');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run enabled — no database writes.');
        }

        $summary = [
            'processed' => 0,
            'auto_approved' => 0,
            'review_required' => 0,
            'skipped' => 0,
            'rejected' => 0,
        ];

        $batches = $batchId
            ? ImportBatch::query()->whereKey($batchId)->get()
            : ImportBatch::query()->orderByDesc('id')->get();

        if ($batches->isEmpty()) {
            $this->error('No import batches found.');

            return self::FAILURE;
        }

        foreach ($batches as $batch) {
            $this->info("Processing batch #{$batch->id} ({$batch->original_filename})");

            if (! $dryRun) {
                $batchSummary = $service->standardizeBatch($batch, $onlyPending, $limit, true);
            } else {
                $batchSummary = $this->dryRunBatch($batch, $service, $onlyPending, $limit);
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
     * @return array{processed: int, auto_approved: int, review_required: int, skipped: int, rejected: int}
     */
    protected function dryRunBatch(
        ImportBatch $batch,
        ImportRowStandardizationService $service,
        bool $onlyPending,
        ?int $limit,
    ): array {
        $query = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->orderBy('row_number');

        if ($onlyPending) {
            $query->where('standardization_status', 'pending');
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        $summary = [
            'processed' => 0,
            'auto_approved' => 0,
            'review_required' => 0,
            'skipped' => 0,
            'rejected' => 0,
        ];

        foreach ($query->get() as $row) {
            $result = $service->standardizeRow($row, false);
            $summary['processed']++;
            $summary[$result['status_bucket']]++;
        }

        return $summary;
    }
}
