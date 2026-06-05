<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePredictionRequest;
use App\Models\Country;
use App\Models\Prediction;
use App\Models\PricingStatistic;
use App\Services\AI\PredictionNarrativeService;
use App\Services\Import\ImportPipelineReadinessService;
use App\Services\Settings\SettingsService;
use App\Services\Prediction\PredictionOrchestratorService;
use App\Services\Tender\TenderGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AIRecommendationController extends Controller
{
    public function create(
        SettingsService $settings,
        ImportPipelineReadinessService $readiness,
        TenderGroupService $tenderGroupService,
    ): View {
        $tenderGroups = $tenderGroupService->listGroups();

        $countries = Country::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $pricingStatsCount = PricingStatistic::query()->count();
        $lastStatsRefresh = PricingStatistic::query()->max('calculated_at');
        $aiHasKey = $settings->hasEncrypted('ai.api_key');
        $aiInsightsEnabled = (bool) $settings->getBoolean('ai.enable_narrative', false);
        $canGenerateInsights = $aiHasKey && $aiInsightsEnabled;

        $hasCountries = $countries->isNotEmpty();
        $hasTenderGroups = $tenderGroups->isNotEmpty();
        $availability = $readiness->recommendationAvailabilityContext();
        $canSubmit = $hasCountries && $hasTenderGroups && $pricingStatsCount > 0;

        return view('ai.recommendations.create', [
            'tenderGroups' => $tenderGroups,
            'countries' => $countries,
            'recentPredictions' => Prediction::query()
                ->with(['standardizedDrug:id,display_name'])
                ->where('user_id', auth()->id())
                ->latest()
                ->limit(5)
                ->get(),
            'counts' => [
                'tender_programs' => $tenderGroups->count(),
                'countries' => $countries->count(),
                'pricing_statistics' => $pricingStatsCount,
                'recent_predictions' => Prediction::query()->where('user_id', auth()->id())->count(),
            ],
            'ai' => [
                'has_api_key' => $aiHasKey,
                'enable_narrative' => $aiInsightsEnabled,
                'can_generate_insights' => $canGenerateInsights,
            ],
            'hasCountries' => $hasCountries,
            'hasTenderGroups' => $hasTenderGroups,
            'canSubmit' => $canSubmit,
            'canGenerateInsights' => $canGenerateInsights,
            'pricingStatsCount' => $pricingStatsCount,
            'lastStatsRefresh' => $lastStatsRefresh,
            'availability' => $availability,
            'quantityUnits' => ['units', 'tablets', 'capsules', 'vials', 'boxes', 'ampoules'],
        ]);
    }

    public function tenderGroupDrugs(string $groupKey, TenderGroupService $tenderGroupService): JsonResponse
    {
        $group = $tenderGroupService->findGroup($groupKey);

        if ($group === null) {
            return response()->json(['message' => 'Tender program not found.'], 404);
        }

        return response()->json([
            'group' => $group,
            'drugs' => $tenderGroupService->drugsForGroup($groupKey)->values(),
        ]);
    }

    public function store(
        StorePredictionRequest $request,
        PredictionOrchestratorService $orchestrator,
    ): RedirectResponse {
        $prediction = $orchestrator->run($request->user(), $request->validated());

        if ($prediction->status === 'failed') {
            return redirect()
                ->route('ai.recommendations.create')
                ->withInput()
                ->with('error', $prediction->rationale ?? 'Unable to generate a price recommendation. Ensure market statistics exist for this drug and country.');
        }

        return redirect()
            ->route('ai.recommendations.show', $prediction)
            ->with('success', 'Price recommendation generated successfully.');
    }

    public function show(Prediction $prediction, PredictionNarrativeService $insightsService, SettingsService $settings): View
    {
        abort_unless($prediction->user_id === auth()->id(), 403);

        $prediction->load([
            'standardizedDrug',
            'currency',
            'tender.country.region',
            'user',
            'predictionCalculations',
            'predictionScenarios',
            'contextSnapshots',
        ]);

        $scenarioOrder = ['aggressive' => 1, 'balanced' => 2, 'conservative' => 3];
        $scenarios = $prediction->predictionScenarios->sortBy(
            fn ($scenario) => $scenarioOrder[$scenario->scenario_name] ?? 99,
        );

        $calculation = $prediction->predictionCalculations->first();
        $contextSnapshot = $prediction->contextSnapshots->first();
        $breakdown = $calculation?->calculation_details ?? $contextSnapshot?->snapshot_data['calculation_breakdown'] ?? [];
        $fallbackLevel = $breakdown['fallback_level'] ?? $contextSnapshot?->snapshot_data['fallback_level'] ?? 'country';
        $marketDataScope = \App\Enums\PredictionFallbackLevel::tryFrom($fallbackLevel)?->label() ?? ucfirst($fallbackLevel);

        $tenderContext = $contextSnapshot?->snapshot_data['tender_context'] ?? null;

        $aiHasKey = $settings->hasEncrypted('ai.api_key');
        $aiInsightsEnabled = (bool) $settings->getBoolean('ai.enable_narrative', false);

        return view('ai.recommendations.show', [
            'prediction' => $prediction,
            'calculation' => $calculation,
            'scenarios' => $scenarios,
            'contextSnapshot' => $contextSnapshot,
            'tenderContext' => $tenderContext,
            'breakdown' => $breakdown,
            'marketDataScope' => $marketDataScope,
            'riskBreakdown' => $breakdown['risk_breakdown'] ?? null,
            'aiInsights' => $insightsService->extractInsights($prediction),
            'aiInsightsStatus' => $insightsService->insightsStatus($prediction),
            'aiInsightsMessage' => $insightsService->insightsMessage($prediction),
            'canRegenerateInsights' => $aiHasKey && $aiInsightsEnabled && $prediction->status === 'completed',
        ]);
    }

    public function regenerateInsights(
        Prediction $prediction,
        PredictionNarrativeService $insightsService,
    ): RedirectResponse {
        abort_unless($prediction->user_id === auth()->id(), 403);

        if ($prediction->status !== 'completed') {
            return redirect()
                ->route('ai.recommendations.show', $prediction)
                ->with('error', 'AI insights can only be generated for completed recommendations.');
        }

        $result = $insightsService->generateForPrediction($prediction->fresh(), [
            'force_regenerate' => true,
        ]);

        if (($result['status'] ?? null) === 'success') {
            return redirect()
                ->route('ai.recommendations.show', $prediction)
                ->with('success', 'AI strategic insights regenerated successfully.');
        }

        if (($result['status'] ?? null) === 'skipped') {
            return redirect()
                ->route('ai.recommendations.show', $prediction)
                ->with('error', $result['message'] ?? 'AI insights could not be generated.');
        }

        return redirect()
            ->route('ai.recommendations.show', $prediction)
            ->with('error', $result['message'] ?? 'AI insights are not available right now.');
    }
}
