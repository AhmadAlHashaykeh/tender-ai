<?php

namespace App\Http\Controllers;

use App\Models\PricingStatistic;
use Illuminate\View\View;

class PricingStatisticsController extends Controller
{
    public function index(): View
    {
        $statistics = PricingStatistic::query()
            ->with(['standardizedDrug', 'country', 'region', 'topWinnerCompany', 'currency'])
            ->whereNotNull('country_id')
            ->orderByDesc('calculated_at')
            ->orderByDesc('award_count')
            ->paginate(50);

        return view('statistics.pricing.index', [
            'statistics' => $statistics,
        ]);
    }
}
