<?php

namespace App\Console\Commands;

use App\Services\Queue\QueueHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ProcessPendingQueueCommand extends Command
{
    protected $signature = 'queue:process-pending
                            {--max-jobs=25 : Maximum jobs to process in this run}
                            {--timeout=120 : Seconds each job may run}';

    protected $description = 'Process pending queue jobs (shared-host friendly; run via cron every minute)';

    public function handle(): int
    {
        $maxJobs = max(1, (int) $this->option('max-jobs'));
        $timeout = max(30, (int) $this->option('timeout'));

        $processed = 0;

        for ($i = 0; $i < $maxJobs; $i++) {
            $exitCode = Artisan::call('queue:work', [
                '--once' => true,
                '--stop-when-empty' => true,
                '--timeout' => $timeout,
                '--tries' => 3,
            ]);

            if ($exitCode !== 0) {
                $this->error(trim(Artisan::output()) ?: 'queue:work failed');

                return self::FAILURE;
            }

            $output = trim(Artisan::output());

            if ($output === '' || str_contains(strtolower($output), 'no jobs')) {
                break;
            }

            $processed++;
            app(QueueHealthService::class)->recordJobProcessed();
        }

        $this->info("Processed {$processed} queue job(s).");

        return self::SUCCESS;
    }
}
