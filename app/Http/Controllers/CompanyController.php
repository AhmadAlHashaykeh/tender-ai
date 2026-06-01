<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Company\CompanyIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function __construct(
        protected CompanyIntelligenceService $intelligenceService,
    ) {}

    public function index(Request $request): View
    {
        return view('companies.index', [
            'companies' => $this->intelligenceService->paginateIndex($request),
            'intel' => $this->intelligenceService,
            'stats' => $this->intelligenceService->indexSummaryStats($request),
            'filterOptions' => $this->intelligenceService->filterOptions(),
            'filters' => $this->intelligenceService->activeFilters($request),
            'perPageOptions' => CompanyIntelligenceService::PER_PAGE_OPTIONS,
            'bidStatuses' => CompanyIntelligenceService::BID_STATUSES,
        ]);
    }

    public function show(Request $request, Company $company): View
    {
        $company->load(['country', 'companyAliases']);

        $kpis = $this->intelligenceService->profileKpis($company);

        return view('companies.show', [
            'company' => $company,
            'intel' => $this->intelligenceService,
            'kpis' => $kpis,
            'bidHistory' => $this->intelligenceService->paginateBidHistory($company, $request),
            'drugSummary' => $this->intelligenceService->drugSummary($company),
            'countrySummary' => $this->intelligenceService->countrySummary($company),
            'perPageOptions' => CompanyIntelligenceService::PER_PAGE_OPTIONS,
        ]);
    }
}
