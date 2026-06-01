<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\StoreUserRequest;
use App\Http\Requests\Settings\UpdateAiSettingsRequest;
use App\Http\Requests\Settings\UpdateGeneralSettingsRequest;
use App\Http\Requests\Settings\UpdatePredictionSettingsRequest;
use App\Http\Requests\Settings\UpdateStandardizationSettingsRequest;
use App\Models\Currency;
use App\Models\ImportBatch;
use App\Models\Prediction;
use App\Models\PricingStatistic;
use App\Models\User;
use App\Services\AI\PredictionNarrativeService;
use App\Services\Settings\OpenAiConnectionTestService;
use App\Services\Settings\SettingsService;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        protected SettingsService $settings,
        protected OpenAiConnectionTestService $openAiTest,
        protected PredictionNarrativeService $narrativeService,
    ) {}

    public function index(Request $request): View
    {
        $activeTab = $request->query('tab', old('_settings_tab', session('settings_tab', 'general')));

        $latestStat = PricingStatistic::query()->latest('updated_at')->value('updated_at');

        $overview = [
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'db_driver' => ucfirst(DB::getDefaultConnection()),
            'environment' => ucfirst(app()->environment()),
            'queue_driver' => ucfirst(config('queue.default', 'sync')),
            'prediction_engine' => $this->settings->getString('prediction.calculation_model_version', 'v1.0'),
            'ai_provider' => ucfirst($this->settings->getString('ai.provider', 'openai')),
            'ai_model' => $this->settings->getString('ai.default_model', 'gpt-4o-mini'),
            'stats_last_refresh' => $latestStat?->format('M j, Y H:i') ?? 'Never',
            'total_predictions' => Prediction::query()->count(),
            'total_imports' => ImportBatch::query()->count(),
            'total_users' => User::query()->count(),
        ];

        return view('settings.index', [
            'activeTab' => $activeTab,
            'overview' => $overview,
            'general' => $this->settings->getGroupForDisplay('general', [
                'organization_name',
                'default_currency',
                'date_format',
                'rows_per_page' => ['type' => 'integer'],
                'timezone',
                'language',
            ]),
            'prediction' => $this->settings->getGroupForDisplay('prediction', [
                'calculation_model_version',
                'backend_only_confidence_threshold' => ['type' => 'integer'],
                'trend_adjustment_cap' => ['type' => 'float'],
                'aggressive_discount_percent' => ['type' => 'float'],
                'conservative_premium_percent' => ['type' => 'float'],
                'large_quantity_multiplier' => ['type' => 'float'],
                'large_quantity_discount_percent' => ['type' => 'float'],
                'small_quantity_multiplier' => ['type' => 'float'],
                'small_quantity_premium_percent' => ['type' => 'float'],
            ]),
            'standardization' => $this->settings->getGroupForDisplay('standardization', [
                'drug_auto_approve_min' => ['type' => 'integer'],
                'company_auto_approve_min' => ['type' => 'integer'],
                'row_auto_approve_min' => ['type' => 'integer'],
                'ai_auto_approve_min' => ['type' => 'integer'],
                'fuzzy_auto_approve_min' => ['type' => 'integer'],
                'max_ai_calls_per_batch' => ['type' => 'integer'],
                'enable_ai_assist' => ['type' => 'boolean'],
            ]),
            'ai' => array_merge(
                $this->settings->getGroupForDisplay('ai', [
                    'provider',
                    'default_model',
                    'advanced_model',
                    'temperature' => ['type' => 'float'],
                    'max_tokens' => ['type' => 'integer'],
                    'timeout_seconds' => ['type' => 'integer'],
                    'enable_narrative' => ['type' => 'boolean'],
                    'narrative_min_confidence' => ['type' => 'integer'],
                    'enable_standardization_assist' => ['type' => 'boolean'],
                    'rate_limit_per_user_per_hour' => ['type' => 'integer'],
                    'monthly_token_budget' => ['type' => 'integer'],
                    'system_prompt_version',
                ]),
                [
                    'api_key_masked' => $this->settings->maskEncrypted('ai.api_key'),
                    'has_api_key' => $this->settings->hasEncrypted('ai.api_key'),
                ],
            ),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(),
            'timezones' => DateTimeZone::listIdentifiers(),
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function updateGeneral(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->settings->updateGroup('general', [
            'organization_name' => ['value' => $validated['organization_name']],
            'default_currency' => ['value' => $validated['default_currency']],
            'date_format' => ['value' => $validated['date_format']],
            'rows_per_page' => ['value' => $validated['rows_per_page'], 'type' => 'integer'],
            'timezone' => ['value' => $validated['timezone']],
            'language' => ['value' => $validated['language']],
        ]);

        return $this->redirectWithSuccess('general', 'General settings saved.');
    }

    public function updatePrediction(UpdatePredictionSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->settings->updateGroup('prediction', [
            'calculation_model_version' => ['value' => $validated['calculation_model_version']],
            'backend_only_confidence_threshold' => ['value' => $validated['backend_only_confidence_threshold'], 'type' => 'integer'],
            'trend_adjustment_cap' => ['value' => $validated['trend_adjustment_cap'], 'type' => 'float'],
            'aggressive_discount_percent' => ['value' => $validated['aggressive_discount_percent'], 'type' => 'float'],
            'conservative_premium_percent' => ['value' => $validated['conservative_premium_percent'], 'type' => 'float'],
            'large_quantity_multiplier' => ['value' => $validated['large_quantity_multiplier'], 'type' => 'float'],
            'large_quantity_discount_percent' => ['value' => $validated['large_quantity_discount_percent'], 'type' => 'float'],
            'small_quantity_multiplier' => ['value' => $validated['small_quantity_multiplier'], 'type' => 'float'],
            'small_quantity_premium_percent' => ['value' => $validated['small_quantity_premium_percent'], 'type' => 'float'],
        ]);

        return $this->redirectWithSuccess('prediction', 'Prediction settings saved.');
    }

    public function updateStandardization(UpdateStandardizationSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->settings->updateGroup('standardization', [
            'drug_auto_approve_min' => ['value' => $validated['drug_auto_approve_min'], 'type' => 'integer'],
            'company_auto_approve_min' => ['value' => $validated['company_auto_approve_min'], 'type' => 'integer'],
            'row_auto_approve_min' => ['value' => $validated['row_auto_approve_min'], 'type' => 'integer'],
            'ai_auto_approve_min' => ['value' => $validated['ai_auto_approve_min'], 'type' => 'integer'],
            'fuzzy_auto_approve_min' => ['value' => $validated['fuzzy_auto_approve_min'], 'type' => 'integer'],
            'max_ai_calls_per_batch' => ['value' => $validated['max_ai_calls_per_batch'], 'type' => 'integer'],
            'enable_ai_assist' => ['value' => $validated['enable_ai_assist'], 'type' => 'boolean'],
        ]);

        return $this->redirectWithSuccess('standardization', 'Standardization settings saved.');
    }

    public function updateAi(UpdateAiSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->settings->updateGroup('ai', [
            'provider' => ['value' => $validated['provider']],
            'default_model' => ['value' => $validated['default_model']],
            'advanced_model' => ['value' => $validated['advanced_model'] ?? ''],
            'temperature' => ['value' => $validated['temperature'], 'type' => 'float'],
            'max_tokens' => ['value' => $validated['max_tokens'], 'type' => 'integer'],
            'timeout_seconds' => ['value' => $validated['timeout_seconds'], 'type' => 'integer'],
            'enable_narrative' => ['value' => $validated['enable_narrative'], 'type' => 'boolean'],
            'narrative_min_confidence' => ['value' => $validated['narrative_min_confidence'], 'type' => 'integer'],
            'enable_standardization_assist' => ['value' => $validated['enable_standardization_assist'], 'type' => 'boolean'],
            'rate_limit_per_user_per_hour' => ['value' => $validated['rate_limit_per_user_per_hour'], 'type' => 'integer'],
            'monthly_token_budget' => ['value' => $validated['monthly_token_budget'] ?? '', 'type' => 'integer'],
            'system_prompt_version' => ['value' => $validated['system_prompt_version']],
        ]);

        if (filled($validated['api_key'] ?? null)) {
            $this->settings->setEncrypted('ai.api_key', $validated['api_key']);
        }

        return $this->redirectWithSuccess('ai', 'AI settings saved.');
    }

    public function destroyAiKey(): RedirectResponse
    {
        $this->settings->removeEncrypted('ai.api_key');

        return $this->redirectWithSuccess('ai', 'API key removed.');
    }

    public function testAiConnection(): RedirectResponse
    {
        $result = $this->openAiTest->test();

        $flashKey = $result['success'] ? 'success' : 'error';
        $statusKey = $result['success'] ? 'ai_connection_success' : 'ai_connection_failed';

        return redirect()
            ->route('settings.index', ['tab' => 'ai'])
            ->with($flashKey, $result['message'])
            ->with($statusKey, true);
    }

    public function testAiNarrative(Request $request): RedirectResponse
    {
        $result = $this->narrativeService->generateTestNarrative([
            'drug' => 'Mock Amoxicillin 500mg',
            'backend_recommended_price' => 12.40,
            'currency' => 'USD',
            'trend' => 'stable',
            'quantity_factor' => 0.98,
            'confidence_score' => 72,
            'risk_level' => 'medium',
            'fallback_level' => 'country',
            'historical_award_count' => 6,
        ], $request->user()?->id);

        if ($result['success'] ?? false) {
            return redirect()
                ->route('settings.index', ['tab' => 'ai'])
                ->with('success', 'AI narrative test successful: '.\Illuminate\Support\Str::limit((string) $result['content'], 180))
                ->with('ai_connection_success', true);
        }

        return redirect()
            ->route('settings.index', ['tab' => 'ai'])
            ->with('error', $result['message'] ?? 'AI narrative test failed safely.')
            ->with('ai_connection_failed', true);
    }

    public function storeUser(StoreUserRequest $request): RedirectResponse
    {
        User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        return $this->redirectWithSuccess('users', 'User created successfully.');
    }

    public function toggleUserStatus(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return $this->redirectWithSuccess('users', 'You cannot deactivate your own account.', 'error');
        }

        $user->update(['is_active' => ! $user->is_active]);

        $message = $user->is_active ? 'User activated.' : 'User deactivated.';

        return $this->redirectWithSuccess('users', $message);
    }

    protected function redirectWithSuccess(string $tab, string $message, string $flashKey = 'success'): RedirectResponse
    {
        return redirect()
            ->route('settings.index', ['tab' => $tab])
            ->with($flashKey, $message)
            ->with('settings_tab', $tab);
    }
}
