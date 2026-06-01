<?php

namespace App\Http\Controllers;

use App\Enums\ImportBatchStatus;
use App\Http\Requests\StoreManualImportRequest;
use App\Http\Requests\StoreUploadRequest;
use App\Http\Requests\StoreUpcomingTenderRequest;
use App\Models\Country;
use App\Models\ImportBatch;
use App\Models\Tender;
use App\Services\Import\ImportBatchService;
use App\Services\Import\ImportParserService;
use App\Services\Import\ManualImportService;
use App\Services\Tender\UpcomingTenderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UploadController extends Controller
{
    public function index(ImportParserService $parser): View
    {
        $recentBatches = ImportBatch::query()
            ->with('uploader')
            ->latest()
            ->limit(10)
            ->get();

        return view('uploads.index', [
            'recentBatches' => $recentBatches,
            'countries' => Country::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'expectedHeaders' => $parser->requiredHeaderLabels(),
            'recentUpcomingTenders' => Tender::query()
                ->with('country:id,name')
                ->where('status', 'upcoming')
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }

    public function store(StoreUploadRequest $request, ImportBatchService $importBatchService): RedirectResponse
    {
        $batch = $importBatchService->storeUpload(
            $request->file('file'),
            $request->user()
        );

        $batch->refresh();
        $metadata = $batch->metadata ?? [];

        if ($batch->status === ImportBatchStatus::Failed->value) {
            $message = $metadata['failure_reason']
                ?? 'The file could not be imported. Check the column headers and try again.';

            return redirect()
                ->route('uploads.index')
                ->withErrors(['file' => $message]);
        }

        return redirect()
            ->route('imports.mapping', $batch)
            ->with('success', 'File uploaded. Review the detected column mapping before importing.');
    }

    public function manualStore(
        StoreManualImportRequest $request,
        ManualImportService $manualImportService,
    ): RedirectResponse {
        $batch = $manualImportService->store(
            $request->user(),
            $request->validated()
        );

        $row = $batch->importRows()->first();

        return redirect()
            ->route('imports.show', $batch)
            ->with('success', 'Manual historical row saved as import batch #'.$batch->id
                .($row ? ' (validation: '.$row->validation_status.')' : '')
                .'. Run standardize → materialize → stats:refresh to complete the pipeline.');
    }

    public function storeUpcomingTender(
        StoreUpcomingTenderRequest $request,
        UpcomingTenderService $upcomingTenderService,
    ): RedirectResponse {
        try {
            $tender = $upcomingTenderService->store($request->validated());
        } catch (\InvalidArgumentException $exception) {
            return redirect()
                ->route('uploads.index')
                ->withInput()
                ->withErrors(['country' => $exception->getMessage()]);
        }

        return redirect()
            ->route('uploads.index')
            ->with('success', 'Upcoming tender "'.$tender->title.'" saved. It will appear in AI Recommendations when selecting a tender.');
    }
}
