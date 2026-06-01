<?php

namespace App\Services\DataManagement;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;

class OperationalDataResetService
{
    /**
     * All operational tables targeted by reset (verification uses this list).
     *
     * @var list<string>
     */
    public const BUSINESS_TABLES = [
        'import_batches',
        'import_rows',
        'import_row_duplicates',
        'import_chunks',
        'standardization_chunks',
        'materialization_chunks',
        'standardization_suggestions',
        'standardization_logs',
        'companies',
        'company_aliases',
        'standardized_drugs',
        'drugs',
        'drug_aliases',
        'tenders',
        'tender_items',
        'bid_records',
        'pricing_statistics',
        'cached_market_statistics',
        'outlier_flags',
        'predictions',
        'prediction_calculations',
        'prediction_scenarios',
        'prediction_context_snapshots',
        'prediction_historical_refs',
        'prediction_accuracy_records',
        'ai_usage_logs',
        'audit_logs',
        'import_mapping_templates',
    ];

    /** @var list<string> */
    public const QUEUE_TABLES = [
        'failed_jobs',
        'jobs',
        'job_batches',
    ];

    /**
     * Grouped labels for post-reset verification output.
     *
     * @var array<string, list<string>>
     */
    public const VERIFICATION_GROUPS = [
        'Import data' => [
            'import_batches',
            'import_rows',
            'import_chunks',
            'import_row_duplicates',
            'import_mapping_templates',
        ],
        'Product matching' => [
            'standardization_chunks',
            'standardization_suggestions',
            'standardization_logs',
        ],
        'Materialized data' => [
            'materialization_chunks',
            'companies',
            'company_aliases',
            'drugs',
            'drug_aliases',
            'standardized_drugs',
            'tenders',
            'tender_items',
            'bid_records',
        ],
        'Analytics' => [
            'pricing_statistics',
            'cached_market_statistics',
            'outlier_flags',
        ],
        'Predictions' => [
            'predictions',
            'prediction_calculations',
            'prediction_scenarios',
            'prediction_context_snapshots',
            'prediction_historical_refs',
            'prediction_accuracy_records',
        ],
        'AI' => [
            'ai_usage_logs',
        ],
        'Audit' => [
            'audit_logs',
        ],
        'Jobs' => [
            'failed_jobs',
            'jobs',
            'job_batches',
        ],
    ];

    /**
     * Reference / config tables that must survive reset.
     *
     * @var list<string>
     */
    public const PRESERVED_REFERENCE_TABLES = [
        'users',
        'settings',
        'countries',
        'regions',
        'currencies',
    ];

    /** @var list<string> */
    public const TRUNCATE_ORDER = [
        'prediction_accuracy_records',
        'prediction_historical_refs',
        'prediction_context_snapshots',
        'prediction_scenarios',
        'prediction_calculations',
        'ai_usage_logs',
        'predictions',
        'outlier_flags',
        'cached_market_statistics',
        'pricing_statistics',
        'bid_records',
        'import_row_duplicates',
        'standardization_logs',
        'standardization_suggestions',
        'tender_items',
        'import_rows',
        'import_chunks',
        'standardization_chunks',
        'materialization_chunks',
        'tenders',
        'drug_aliases',
        'drugs',
        'company_aliases',
        'companies',
        'standardized_drugs',
        'import_batches',
        'audit_logs',
        'import_mapping_templates',
        'failed_jobs',
        'jobs',
        'job_batches',
    ];

    /** @var list<string> */
    public const PRESERVED_TABLES = [
        'users',
        'settings',
        'countries',
        'regions',
        'currencies',
        'migrations',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
    ];

    /**
     * @return list<string>
     */
    public function allClearableTables(): array
    {
        return array_values(array_unique([...self::BUSINESS_TABLES, ...self::QUEUE_TABLES]));
    }

    /**
     * @return array<string, int>
     */
    public function rowCountsForQueueTables(): array
    {
        $counts = [];

        foreach (self::QUEUE_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $counts[$table] = (int) DB::table($table)->count();
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    public function rowCountsForBusinessTables(): array
    {
        $counts = [];

        foreach (self::BUSINESS_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $counts[$table] = (int) DB::table($table)->count();
        }

        return $counts;
    }

    /**
     * @return list<string>
     */
    public function missingBusinessTables(): array
    {
        $missing = [];

        foreach (self::BUSINESS_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                $missing[] = $table;
            }
        }

        return $missing;
    }

    /**
     * @return array<string, int> Row counts for existing business tables (must all be 0 after reset).
     */
    public function verifyBusinessTablesEmpty(): array
    {
        $counts = [];

        foreach (self::BUSINESS_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $counts[$table] = (int) DB::table($table)->count();
        }

        return $counts;
    }

    /**
     * @return array<string, array{status: string, tables: array<string, int>}>
     */
    public function verifyByGroup(): array
    {
        $results = [];

        foreach (self::VERIFICATION_GROUPS as $label => $tables) {
            $tableCounts = [];
            $allClean = true;

            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $count = (int) DB::table($table)->count();
                $tableCounts[$table] = $count;

                if ($count > 0) {
                    $allClean = false;
                }
            }

            $results[$label] = [
                'status' => $allClean ? 'CLEAN' : 'NOT CLEAN',
                'tables' => $tableCounts,
            ];
        }

        return $results;
    }

    /**
     * @return array<string, array{status: string, count: int}>
     */
    public function verifyPreservedReferenceData(): array
    {
        $results = [];

        foreach (self::PRESERVED_REFERENCE_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                $results[$table] = ['status' => 'MISSING', 'count' => 0];

                continue;
            }

            $count = (int) DB::table($table)->count();
            $results[$table] = [
                'status' => 'OK',
                'count' => $count,
            ];
        }

        return $results;
    }

    /**
     * @return array{path: string, skipped: bool}
     */
    public function createBackup(): array
    {
        if (app()->environment('testing')) {
            return [
                'path' => 'skipped (testing environment)',
                'skipped' => true,
            ];
        }

        $driver = (string) config('database.default');
        $connection = config('database.connections.'.$driver);

        if (! is_array($connection)) {
            throw new RuntimeException('Database connection configuration is missing.');
        }

        $backupDir = storage_path('app/backups');

        if (! is_dir($backupDir) && ! mkdir($backupDir, 0755, true) && ! is_dir($backupDir)) {
            throw new RuntimeException("Unable to create backup directory: {$backupDir}");
        }

        $timestamp = now()->format('Y-m-d_His');

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $database = (string) ($connection['database'] ?? '');
            $filename = "{$database}_{$timestamp}.sql";
            $path = $backupDir.DIRECTORY_SEPARATOR.$filename;

            $this->runMysqlDump($connection, $path);

            return [
                'path' => $path,
                'skipped' => false,
            ];
        }

        if ($driver === 'sqlite') {
            $database = (string) ($connection['database'] ?? '');

            if ($database === ':memory:') {
                return [
                    'path' => 'skipped (in-memory sqlite)',
                    'skipped' => true,
                ];
            }

            if (! is_file($database)) {
                throw new RuntimeException("SQLite database file not found: {$database}");
            }

            $filename = "sqlite_{$timestamp}.sqlite";
            $path = $backupDir.DIRECTORY_SEPARATOR.$filename;

            if (! copy($database, $path)) {
                throw new RuntimeException("Failed to copy SQLite database to {$path}");
            }

            return [
                'path' => $path,
                'skipped' => false,
            ];
        }

        throw new RuntimeException("Unsupported database driver for backup: {$driver}");
    }

    /**
     * @return array{cleared: array<string, int>, skipped: list<string>}
     */
    public function truncateBusinessTables(): array
    {
        $cleared = [];
        $skipped = [];

        Schema::disableForeignKeyConstraints();

        try {
            foreach (self::TRUNCATE_ORDER as $table) {
                if (! Schema::hasTable($table)) {
                    $skipped[] = $table;

                    continue;
                }

                $cleared[$table] = (int) DB::table($table)->count();
                DB::table($table)->truncate();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return [
            'cleared' => $cleared,
            'skipped' => array_values(array_unique($skipped)),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function rowCountsForPreservedTables(): array
    {
        $counts = [];

        foreach (self::PRESERVED_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $counts[$table] = (int) DB::table($table)->count();
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    protected function runMysqlDump(array $connection, string $path): void
    {
        $mysqldump = $this->resolveMysqlDumpPath();

        if ($mysqldump === null) {
            throw new RuntimeException(
                'mysqldump executable not found. Set MYSQLDUMP_PATH in .env or ensure mysqldump is on PATH.'
            );
        }

        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? '3306');
        $username = (string) ($connection['username'] ?? 'root');
        $password = (string) ($connection['password'] ?? '');
        $database = (string) ($connection['database'] ?? '');

        $command = [
            $mysqldump,
            '-h', $host,
            '-P', $port,
            '-u', $username,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--result-file='.$path,
            $database,
        ];

        if ($password !== '') {
            $command[] = '-p'.$password;
        }

        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            if (is_file($path)) {
                @unlink($path);
            }

            throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput() ?: 'mysqldump failed.'));
        }

        if (! is_file($path) || filesize($path) === 0) {
            throw new RuntimeException("Backup file was not created or is empty: {$path}");
        }
    }

    protected function resolveMysqlDumpPath(): ?string
    {
        $candidates = array_filter([
            env('MYSQLDUMP_PATH'),
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'D:\\xampp\\mysql\\bin\\mysqldump.exe',
            'mysqldump',
            'mysqldump.exe',
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate === 'mysqldump' || $candidate === 'mysqldump.exe') {
                $resolved = $this->resolveExecutableOnPath($candidate);

                if ($resolved !== null) {
                    return $resolved;
                }

                continue;
            }

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function resolveExecutableOnPath(string $executable): ?string
    {
        $finder = PHP_OS_FAMILY === 'Windows'
            ? new Process(['where', $executable])
            : new Process(['which', $executable]);

        $finder->run();

        if (! $finder->isSuccessful()) {
            return null;
        }

        $line = trim(strtok($finder->getOutput(), PHP_EOL));

        return $line !== '' && is_file($line) ? $line : null;
    }
}
