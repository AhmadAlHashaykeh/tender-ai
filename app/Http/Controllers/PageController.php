<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardService;
use App\Services\Reports\ReportsService;
use Illuminate\View\View;

class PageController extends Controller
{
    public function landing(): View
    {
        return view('landing.index');
    }

    public function dashboard(DashboardService $dashboard): View
    {
        $user = auth()->user();

        return view('dashboard.index', [
            'summary' => $dashboard->summaryForUser($user),
            'recentAwardedBid' => $dashboard->recentAwardedBid(),
            'upcomingTender' => $dashboard->nextUpcomingTender(),
            'tenderVolumeByCountry' => $dashboard->tenderVolumeByCountry(),
            'priceTrendsByCountry' => $dashboard->priceTrendsByCountry(),
        ]);
    }

    public function uploads(): View
    {
        return view('uploads.index');
    }

    public function tendersIndex(): View
    {
        return view('tenders.index');
    }

    public function tendersShow(): View
    {
        return view('tenders.show');
    }

    public function drugsIndex(): View
    {
        return view('drugs.index');
    }

    public function drugsShow(): View
    {
        return view('drugs.show');
    }

    public function standardization(): View
    {
        return view('standardization.index');
    }

    public function reportsIndex(ReportsService $reports): View
    {
        return view('reports.index', [
            'summary' => $reports->marketSummary(),
            'topCompanies' => $reports->topCompaniesByAwards(),
            'topDrugs' => $reports->topDrugsByActivity(),
        ]);
    }

    public function reportsCompany(ReportsService $reports): View
    {
        return view('reports.company', [
            'topCompanies' => $reports->topCompaniesByAwards(20),
            'summary' => $reports->marketSummary(),
        ]);
    }

    public function reportsOpportunity(ReportsService $reports): View
    {
        return view('reports.opportunity', [
            'countryOpportunities' => $reports->countryOpportunitySummary(),
            'summary' => $reports->marketSummary(),
        ]);
    }

    public function reportsHistory(ReportsService $reports): View
    {
        return view('reports.history', [
            'performance' => $reports->predictionPerformanceForUser(auth()->user()),
            'recentPredictions' => $reports->recentPredictionsForUser(auth()->user()),
        ]);
    }
}
