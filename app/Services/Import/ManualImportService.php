<?php

namespace App\Services\Import;

use App\Models\ImportBatch;
use App\Models\User;

class ManualImportService
{
    public function __construct(
        protected ImportBatchService $importBatchService,
    ) {}

    /**
     * @param  array<string, mixed>  $canonical
     */
    public function store(User $user, array $canonical): ImportBatch
    {
        return $this->importBatchService->storeManualEntry($user, $canonical);
    }
}
