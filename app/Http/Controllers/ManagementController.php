<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBidRecordRequest;
use App\Models\BidRecord;
use App\Models\Company;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Services\Management\BidRecordManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManagementController extends Controller
{
    public function __construct(
        protected BidRecordManagementService $managementService,
    ) {}

    public function index(Request $request): View
    {
        return view('management.index', [
            'bidRecords' => $this->managementService->paginate($request),
            'stats' => $this->managementService->summaryStats(),
            'filterOptions' => $this->managementService->filterOptions(),
            'filters' => $this->managementService->activeFilters($request),
            'perPageOptions' => BidRecordManagementService::PER_PAGE_OPTIONS,
            'bidStatuses' => BidRecordManagementService::BID_STATUSES,
        ]);
    }

    public function show(BidRecord $bidRecord): View
    {
        $bidRecord->load([
            'tender.country',
            'tenderItem',
            'standardizedDrug',
            'company',
            'country',
            'currency',
            'sourceImportRow.importBatch',
            'importBatch',
        ]);

        return view('management.show', [
            'bidRecord' => $bidRecord,
        ]);
    }

    public function edit(BidRecord $bidRecord): View
    {
        $bidRecord->load(['tender', 'standardizedDrug', 'company', 'country', 'sourceImportRow']);

        return view('management.edit', [
            'bidRecord' => $bidRecord,
            'companies' => Company::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'drugs' => StandardizedDrug::query()->orderBy('display_name')->limit(500)->get(['id', 'code', 'display_name']),
            'tenders' => Tender::query()->with('country')->orderByDesc('id')->limit(500)->get(['id', 'tender_number', 'country_id', 'year', 'version']),
            'bidStatuses' => BidRecordManagementService::BID_STATUSES,
        ]);
    }

    public function update(UpdateBidRecordRequest $request, BidRecord $bidRecord): RedirectResponse
    {
        $this->managementService->updateBidRecord(
            $bidRecord,
            $request->validated(),
            $request->user()?->id,
        );

        return redirect()
            ->route('management.bid-records.edit', $bidRecord)
            ->with('success', 'Bid record updated. Run stats:refresh after editing pricing-related fields.');
    }

    public function toggleExclusion(Request $request, BidRecord $bidRecord): RedirectResponse
    {
        $this->managementService->toggleExclusion(
            $bidRecord,
            $request->input('exclusion_reason'),
        );

        $message = $bidRecord->fresh()->excluded_from_stats
            ? 'Record excluded from statistics.'
            : 'Record included in statistics again.';

        return redirect()
            ->back()
            ->with('success', $message);
    }
}
