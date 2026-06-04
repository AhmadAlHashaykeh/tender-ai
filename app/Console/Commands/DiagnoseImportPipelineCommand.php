<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Services\Import\ImportPipelineDiagnosticsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseImportPipelineCommand extends Command
{
    protected $signature = 'imports:diagnose
                            {batch? : Import batch ID}
                            {--json : Output as JSON}';

    protected $description = 'Inspect import pipeline database state (safe read-only diagnostics)';

    public function handle(ImportPipelineDiagnosticsService $diagnostics): int
    {
        $batchId = $this->argument('batch');

        if ($batchId !== null) {
            $batch = ImportBatch::query()->find($batchId);

            if (! $batch) {
                $this->error("Import batch {$batchId} not found.");

                return self::FAILURE;
            }

            $this->renderBatchDiagnostics($diagnostics->forBatch($batch), (int) $batch->id);

            return self::SUCCESS;
        }

        $this->info('Global queue');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Queue connection', config('queue.default')],
                ['Pending jobs', $this->tableExists('jobs') ? (int) DB::table('jobs')->count() : 'n/a'],
                ['Failed jobs', $this->tableExists('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 'n/a'],
            ],
        );

        $batches = ImportBatch::query()->latest()->limit(10)->get();

        if ($batches->isEmpty()) {
            $this->warn('No import batches found.');

            return self::SUCCESS;
        }

        foreach ($batches as $batch) {
            $this->newLine();
            $this->renderBatchDiagnostics($diagnostics->forBatch($batch), (int) $batch->id);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderBatchDiagnostics(array $data, int $batchId): void
    {
        if ($this->option('json')) {
            $this->line(json_encode(['batch_id' => $batchId] + $data, JSON_PRETTY_PRINT));

            return;
        }

        $this->info("Import batch #{$batchId}");
        $this->table(
            ['Metric', 'Value'],
            collect($data)
                ->except(['failed_job_details', 'related_entities'])
                ->map(fn ($value, $key) => [$key, is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value])
                ->values()
                ->all(),
        );
    }

    protected function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }
}
