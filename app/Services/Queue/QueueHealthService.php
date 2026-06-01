<?php

namespace App\Services\Queue;

use App\Models\ImportBatch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QueueHealthService
{
    public const LAST_PROCESSED_CACHE_KEY = 'queue:last_job_processed_at';

    public function recordJobProcessed(): void
    {
        Cache::put(self::LAST_PROCESSED_CACHE_KEY, now()->toIso8601String(), now()->addDay());
    }

    public function lastProcessedAt(): ?Carbon
    {
        $value = Cache::get(self::LAST_PROCESSED_CACHE_KEY);

        return $value ? Carbon::parse($value) : null;
    }

    public function usesDatabaseQueue(): bool
    {
        return config('queue.default') === 'database';
    }

    public function pendingJobCount(): int
    {
        if (! $this->usesDatabaseQueue()) {
            return 0;
        }

        return (int) DB::table('jobs')->count();
    }

    public function isWorkerStale(?int $staleMinutes = null): bool
    {
        if (! $this->usesDatabaseQueue()) {
            return false;
        }

        $staleMinutes ??= max(1, (int) config('import.queue_worker_stale_minutes', 5));
        $last = $this->lastProcessedAt();

        if ($last === null) {
            return $this->pendingJobCount() > 0 || $this->hasActivePipelineBatches();
        }

        return $last->lt(now()->subMinutes($staleMinutes));
    }

    public function shouldWarnAdmin(): bool
    {
        if (! $this->usesDatabaseQueue()) {
            return false;
        }

        if (! $this->isWorkerStale()) {
            return false;
        }

        return $this->pendingJobCount() > 0 || $this->hasActivePipelineBatches();
    }

    /**
     * @return array{should_warn: bool, message: string, last_processed_at: ?string, pending_jobs: int}
     */
    public function adminStatus(): array
    {
        $shouldWarn = $this->shouldWarnAdmin();
        $last = $this->lastProcessedAt();

        return [
            'should_warn' => $shouldWarn,
            'message' => $shouldWarn
                ? 'Background processor is not running.'
                : 'Background processor is active.',
            'last_processed_at' => $last?->toIso8601String(),
            'pending_jobs' => $this->pendingJobCount(),
        ];
    }

    protected function hasActivePipelineBatches(): bool
    {
        return ImportBatch::query()
            ->where(function ($query): void {
                foreach (['preparing', 'processing'] as $status) {
                    $query->orWhere('metadata->materialization_status', $status);
                }
            })
            ->exists();
    }
}
