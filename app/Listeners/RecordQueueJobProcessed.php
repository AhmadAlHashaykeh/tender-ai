<?php

namespace App\Listeners;

use App\Services\Queue\QueueHealthService;
use Illuminate\Queue\Events\JobProcessed;

class RecordQueueJobProcessed
{
    public function __construct(
        protected QueueHealthService $queueHealth,
    ) {}

    public function handle(JobProcessed $event): void
    {
        $this->queueHealth->recordJobProcessed();
    }
}
