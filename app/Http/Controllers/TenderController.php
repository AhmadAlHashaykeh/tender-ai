<?php

namespace App\Http\Controllers;

use App\Models\Tender;
use App\Services\Tender\TenderIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenderController extends Controller
{
    public function __construct(
        protected TenderIntelligenceService $intelligenceService,
    ) {}

    public function index(Request $request): View
    {
        return view('tenders.index', [
            'tenders' => $this->intelligenceService->paginateIndex($request),
            'intel' => $this->intelligenceService,
            'stats' => $this->intelligenceService->indexSummaryStats($request),
            'filterOptions' => $this->intelligenceService->filterOptions(),
            'filters' => $this->intelligenceService->activeFilters($request),
            'perPageOptions' => TenderIntelligenceService::PER_PAGE_OPTIONS,
            'bidStatuses' => TenderIntelligenceService::BID_STATUSES,
        ]);
    }

    public function show(Request $request, Tender $tender): View
    {
        $tender->load(['country']);

        return view('tenders.show', [
            'tender' => $tender,
            'intel' => $this->intelligenceService,
            'kpis' => $this->intelligenceService->profileKpis($tender),
            'bidHistory' => $this->intelligenceService->paginateBidHistory($tender, $request),
            'companySummary' => $this->intelligenceService->companySummary($tender),
            'drugSummary' => $this->intelligenceService->drugSummary($tender),
            'perPageOptions' => TenderIntelligenceService::PER_PAGE_OPTIONS,
        ]);
    }
}
