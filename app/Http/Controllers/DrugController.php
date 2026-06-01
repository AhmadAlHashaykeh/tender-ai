<?php

namespace App\Http\Controllers;

use App\Models\StandardizedDrug;
use App\Services\Drug\DrugIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DrugController extends Controller
{
    public function __construct(
        protected DrugIntelligenceService $intelligenceService,
    ) {}

    public function index(Request $request): View
    {
        return view('drugs.index', [
            'drugs' => $this->intelligenceService->paginateIndex($request),
            'intel' => $this->intelligenceService,
            'stats' => $this->intelligenceService->indexSummaryStats($request),
            'filterOptions' => $this->intelligenceService->filterOptions(),
            'filters' => $this->intelligenceService->activeFilters($request),
            'perPageOptions' => DrugIntelligenceService::PER_PAGE_OPTIONS,
            'bidStatuses' => DrugIntelligenceService::BID_STATUSES,
        ]);
    }

    public function show(Request $request, StandardizedDrug $drug): View
    {
        $drug->load(['drugAliases']);

        return view('drugs.show', [
            'drug' => $drug,
            'intel' => $this->intelligenceService,
            'kpis' => $this->intelligenceService->profileKpis($drug),
            'bidHistory' => $this->intelligenceService->paginateBidHistory($drug, $request),
            'pricingStatistics' => $this->intelligenceService->pricingStatisticsSection($drug),
            'companySummary' => $this->intelligenceService->companySummary($drug),
            'countrySummary' => $this->intelligenceService->countrySummary($drug),
            'perPageOptions' => DrugIntelligenceService::PER_PAGE_OPTIONS,
        ]);
    }
}
