<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use App\Services\DataManagement\OperationalDataResetService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ResetDataCommand extends Command
{
    protected $signature = 'tenderai:reset-data
                            {--force : Run without interactive confirmation}
                            {--no-cache-clear : Skip running optimize:clear after reset}';

    protected $description = 'Back up and truncate all operational TenderAI business data (preserves users and settings)';

    protected ?string $lastBackupPath = null;

    protected bool $lastBackupSkipped = false;

    public function handle(OperationalDataResetService $resetService): int
    {
        $this->components->warn('This command permanently deletes ALL operational/business data.');
        $this->line('  Imports, standardization, tenders, bids, statistics, predictions, AI logs, and audit logs will be removed.');
        $this->line('  Preserved: users, settings, countries, regions, currencies, migrations, and Laravel cache/session tables.');
        $this->line('  Also cleared for testing: jobs, failed_jobs, job_batches (stale queue references).');
        $this->newLine();

        $beforeCounts = $resetService->rowCountsForBusinessTables();
        $queueBeforeCounts = $resetService->rowCountsForQueueTables();
        $totalRows = array_sum($beforeCounts) + array_sum($queueBeforeCounts);

        if ($totalRows === 0 && $this->queueTablesAreEmpty($resetService)) {
            $this->components->info('No business or queue data found. Nothing to reset.');
            $this->printGroupedVerification($resetService);
            $this->verifyApplicationHealth();

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Business tables', (string) count($beforeCounts));
        $this->components->twoColumnDetail('Queue tables', (string) count($queueBeforeCounts));
        $this->components->twoColumnDetail('Rows to delete', (string) $totalRows);
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Create a backup and delete all business data?', false)) {
            $this->components->warn('Aborted. No changes were made.');

            return self::FAILURE;
        }

        try {
            $this->components->info('Creating database backup…');
            $backup = $resetService->createBackup();

            $this->lastBackupPath = $backup['path'];
            $this->lastBackupSkipped = $backup['skipped'];

            if ($backup['skipped']) {
                $this->line('  Backup skipped: '.$backup['path']);
            } else {
                $this->components->twoColumnDetail('Backup saved', $backup['path']);
            }

            $this->newLine();
            $this->components->info('Truncating operational tables (FK checks disabled)…');
            $result = $resetService->truncateBusinessTables();

            $this->printSummary(
                $result['cleared'],
                $result['skipped'],
                $resetService->rowCountsForPreservedTables(),
                $resetService->missingBusinessTables(),
            );

            if (! $this->verifyBusinessTablesEmpty($resetService)) {
                return self::FAILURE;
            }

            $this->printGroupedVerification($resetService);

            if (! $this->option('no-cache-clear')) {
                $this->clearApplicationCache();
            }

            if (! $this->verifyApplicationHealth()) {
                return self::FAILURE;
            }

            $this->printFinalReport($resetService, $result['cleared'], $result['skipped']);
            $this->printPostResetInstructions(! $this->option('no-cache-clear'));

            $this->newLine();
            $this->components->info('TenderAI business data reset complete. Ready for fresh Excel import testing.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error('Reset failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, int>  $cleared
     * @param  list<string>  $skippedDuringTruncate
     * @param  array<string, int>  $preserved
     * @param  list<string>  $missingBusinessTables
     */
    protected function printSummary(
        array $cleared,
        array $skippedDuringTruncate,
        array $preserved,
        array $missingBusinessTables,
    ): void {
        $this->newLine();
        $this->components->info('Tables cleared');

        $this->table(
            ['Table', 'Rows deleted'],
            collect($cleared)
                ->map(fn (int $count, string $table) => [$table, $count])
                ->values()
                ->all()
        );

        $chunkTables = ['import_chunks', 'standardization_chunks', 'materialization_chunks'];
        $chunksCleared = array_intersect(array_keys($cleared), $chunkTables);

        if ($chunksCleared !== []) {
            $this->components->twoColumnDetail(
                'Chunk tables cleared',
                implode(', ', array_values($chunksCleared))
            );
        } else {
            $existingChunks = array_diff($chunkTables, $missingBusinessTables, $skippedDuringTruncate);
            $this->components->twoColumnDetail(
                'Chunk tables cleared',
                $existingChunks === [] ? 'none (tables not present)' : 'none (no rows)'
            );
        }

        if ($missingBusinessTables !== [] || $skippedDuringTruncate !== []) {
            $this->newLine();
            $this->components->info('Tables skipped (do not exist)');

            $skipped = array_values(array_unique([...$missingBusinessTables, ...$skippedDuringTruncate]));
            sort($skipped);

            foreach ($skipped as $table) {
                $this->line("  - {$table}");
            }
        }

        $this->newLine();
        $this->components->info('Tables preserved');

        $this->table(
            ['Table', 'Rows remaining'],
            collect($preserved)
                ->map(fn (int $count, string $table) => [$table, $count])
                ->values()
                ->all()
        );
    }

    protected function verifyBusinessTablesEmpty(OperationalDataResetService $resetService): bool
    {
        $remaining = $resetService->verifyBusinessTablesEmpty();
        $nonZero = array_filter($remaining, fn (int $count) => $count > 0);

        $queueRemaining = [];
        foreach (OperationalDataResetService::QUEUE_TABLES as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            $count = (int) \Illuminate\Support\Facades\DB::table($table)->count();
            $queueRemaining[$table] = $count;

            if ($count > 0) {
                $nonZero[$table] = $count;
            }
        }

        $this->newLine();
        $this->components->info('Post-reset verification (all operational tables must be 0 rows)');

        $rows = collect($remaining)
            ->map(fn (int $count, string $table) => [$table, $count])
            ->values()
            ->all();

        foreach ($queueRemaining as $table => $count) {
            $rows[] = [$table, $count];
        }

        $this->table(['Table', 'Rows remaining'], $rows);

        if ($nonZero !== []) {
            $this->components->error('Reset incomplete: some operational tables still contain data.');

            return false;
        }

        $this->components->twoColumnDetail('Operational tables', 'all 0 rows');

        return true;
    }

    protected function printGroupedVerification(OperationalDataResetService $resetService): void
    {
        $this->newLine();
        $this->components->info('Verification by area');

        foreach ($resetService->verifyByGroup() as $label => $group) {
            $status = $group['status'];
            $line = "{$label}: {$status}";

            if ($status === 'NOT CLEAN') {
                $dirty = collect($group['tables'])
                    ->filter(fn (int $count) => $count > 0)
                    ->keys()
                    ->implode(', ');
                $line .= " ({$dirty})";
            }

            $this->line('  '.$line);
        }

        $this->newLine();
        $this->components->info('System data preserved');

        foreach ($resetService->verifyPreservedReferenceData() as $table => $info) {
            $label = ucfirst(str_replace('_', ' ', $table));
            $suffix = $info['count'] > 0 ? " ({$info['count']} rows)" : '';
            $this->line("  {$label}: {$info['status']}{$suffix}");
        }
    }

    /**
     * @param  array<string, int>  $cleared
     * @param  list<string>  $skippedDuringTruncate
     */
    protected function printFinalReport(
        OperationalDataResetService $resetService,
        array $cleared,
        array $skippedDuringTruncate,
    ): void {
        $missing = $resetService->missingBusinessTables();
        $queueCleared = array_values(array_intersect(
            array_keys($cleared),
            OperationalDataResetService::QUEUE_TABLES,
        ));

        $this->newLine();
        $this->components->info('Final report');

        $this->components->twoColumnDetail('Tables cleared', (string) count($cleared));
        $this->components->twoColumnDetail(
            'Tables preserved',
            (string) count($resetService->rowCountsForPreservedTables())
        );

        $skipped = array_values(array_unique([...$missing, ...$skippedDuringTruncate]));
        $this->components->twoColumnDetail(
            'Tables missing (skipped)',
            $skipped === [] ? 'none' : implode(', ', $skipped)
        );

        if ($this->lastBackupPath !== null) {
            $backupLabel = $this->lastBackupSkipped
                ? 'skipped — '.$this->lastBackupPath
                : $this->lastBackupPath;
            $this->components->twoColumnDetail('Backup location', $backupLabel);
        }

        $this->components->twoColumnDetail(
            'Queue tables cleared',
            $queueCleared === [] ? 'none' : implode(', ', $queueCleared)
        );

        $this->components->twoColumnDetail('System ready', 'Yes — upload fresh Excel to test automated pipeline');
    }

    protected function clearApplicationCache(): void
    {
        $this->newLine();
        $this->components->info('Clearing Laravel caches…');

        $exitCode = $this->call('optimize:clear');

        if ($exitCode !== self::SUCCESS) {
            $this->components->warn('optimize:clear returned a non-zero exit code. Run it manually if needed.');
        }
    }

    protected function printPostResetInstructions(bool $cacheAlreadyCleared): void
    {
        $this->newLine();
        $this->components->info('Next steps — automated import pipeline test');

        $step = 1;

        if (! $cacheAlreadyCleared) {
            $this->line('  '.$step++.'. php artisan optimize:clear');
        }

        $this->line('  '.$step++.'. php artisan queue:work --queue=default --tries=3 --timeout=3600');
        $this->line('  '.$step++.'. Upload ~500-row Excel via the import hub');
        $this->line('  '.$step++.'. Confirm detected columns — pipeline should auto: import → validate → match → prepare → stats');
        $this->line('     (no manual Run Standardization / Materialize / Refresh Stats)');
        $this->line('  '.$step++.'. Expect final state: data ready + predictions available');
    }

    protected function queueTablesAreEmpty(OperationalDataResetService $resetService): bool
    {
        foreach (OperationalDataResetService::QUEUE_TABLES as $table) {
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            if ((int) \Illuminate\Support\Facades\DB::table($table)->count() > 0) {
                return false;
            }
        }

        return true;
    }

    protected function verifyApplicationHealth(): bool
    {
        $this->newLine();
        $this->components->info('Verifying application health…');

        try {
            DB::connection()->getPdo();
            $userCount = User::query()->count();
            $settingCount = Setting::query()->count();

            $this->components->twoColumnDetail('Database connection', 'OK');
            $this->components->twoColumnDetail('Users', (string) $userCount);
            $this->components->twoColumnDetail('Settings', (string) $settingCount);

            return true;
        } catch (Throwable $exception) {
            $this->components->error('Health check failed: '.$exception->getMessage());

            return false;
        }
    }
}
