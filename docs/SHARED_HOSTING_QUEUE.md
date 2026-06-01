# Queue Processing on Shared Hosting

TenderAI never runs import pipeline jobs inside HTTP requests. All stages are queued on the `database` driver.

## Cron setup

Add to crontab (every minute):

```bash
* * * * * cd /path/to/tendar-ai && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs:

```bash
php artisan queue:process-pending --max-jobs=25 --timeout=120
```

That command calls `queue:work --once --stop-when-empty` in a loop until the queue is empty or the job limit is reached.

## Automated pipeline order

After mapping confirmation, jobs run in sequence:

1. `ProcessImportBatchJob` — import rows  
2. `StandardizeImportBatchJob` — product matching  
3. `MaterializeImportBatchJob` — bid records / market data  
4. `RefreshImportStatisticsJob` — pricing statistics (required before **Ready**)  

No `php artisan stats:refresh` is required for normal uploads.

## Environment

```env
QUEUE_CONNECTION=database
IMPORT_PIPELINE_AUTOMATION=true
IMPORT_SINGLE_JOB_MAX_ROWS=500
```

## Local development

Either use the same cron/scheduler, or run a worker:

```bash
php artisan queue:work --queue=default --tries=3 --timeout=3600
```

Do **not** set `QUEUE_CONNECTION=sync` if you want to test the real SaaS flow.

## What was removed

- `ProcessPendingQueueOnRequest` middleware (ran `queue:work` after every page load and caused 60s timeouts)
- `dispatch_sync()` for “small” imports via `IMPORT_SYNC_THRESHOLD`
