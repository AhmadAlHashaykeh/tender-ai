<?php

namespace App\Services\AI;

use App\Enums\PredictionStatus;
use App\Models\Prediction;
use App\Models\PredictionCalculation;
use App\Models\PredictionContextSnapshot;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Str;
use Throwable;

class PredictionNarrativeService
{
    public const INSIGHT_SECTIONS = [
        'market_overview' => 'Market Overview',
        'competition_analysis' => 'Competition Analysis',
        'discount_review' => 'Discount Review',
        'risk_commentary' => 'Risk Commentary',
        'strategic_recommendation' => 'Strategic Recommendation',
    ];

    public function __construct(
        protected OpenAIService $openAI,
        protected SettingsService $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function generateForPrediction(Prediction $prediction, array $options = []): array
    {
        $forceRegenerate = (bool) ($options['force_regenerate'] ?? false);

        if (! $forceRegenerate && $this->hasStoredInsights($prediction)) {
            return [
                'success' => true,
                'status' => 'cached',
                'insights' => $this->extractInsights($prediction),
                'message' => 'AI insights loaded from cache.',
            ];
        }

        if (! $this->settings->getBoolean('ai.enable_narrative', false)) {
            return $this->markSkipped($prediction, 'AI insights are not enabled.');
        }

        if (($options['dry_run'] ?? false) === true) {
            return $this->markSkipped($prediction, 'AI insights skipped for dry-run mode.');
        }

        if (! $this->settings->hasEncrypted('ai.api_key')) {
            return $this->markSkipped($prediction, 'AI insights are not available because no OpenAI API key is configured.');
        }

        if ($prediction->status !== PredictionStatus::Completed->value) {
            return $this->markSkipped($prediction, 'AI insights are not available because the recommendation is not completed.');
        }

        $threshold = $this->settings->getInteger('ai.narrative_min_confidence', 50) ?? 50;
        $confidence = (float) ($prediction->confidence_score ?? 0);

        if ($confidence < $threshold) {
            return $this->markSkipped($prediction, "AI insights were not generated because data confidence ({$confidence}%) is below the minimum threshold ({$threshold}%).");
        }

        try {
            $prediction->loadMissing([
                'standardizedDrug',
                'currency',
                'tender.country',
                'predictionCalculations',
                'predictionScenarios',
                'contextSnapshots',
            ]);

            $calculation = $prediction->predictionCalculations->first();
            $contextSnapshot = $prediction->contextSnapshots->first();

            if (! $calculation instanceof PredictionCalculation || ! $contextSnapshot instanceof PredictionContextSnapshot) {
                return $this->markSkipped($prediction, 'AI insights are not available because recommendation context is incomplete.');
            }

            $messages = [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $this->userPrompt($prediction, $contextSnapshot, $calculation)],
            ];

            $response = $this->openAI->chat($messages, [
                'feature' => 'prediction_narrative',
                'user_id' => $prediction->user_id,
                'prediction_id' => $prediction->id,
                'model' => $this->settings->getString('ai.default_model', 'gpt-4o-mini'),
                'temperature' => $this->settings->getFloat('ai.temperature', 0.2),
                'max_tokens' => min($this->settings->getInteger('ai.max_tokens', 800) ?? 800, 1400),
                'timeout' => $this->settings->getInteger('ai.timeout_seconds', 60),
            ]);

            if (! ($response['success'] ?? false)) {
                $prediction->update([
                    'openai_called' => true,
                    'ai_model' => $response['model'] ?? null,
                    'ai_model_used' => $response['model'] ?? null,
                    'ai_response_ms' => $response['response_time_ms'] ?? null,
                    'ai_response_raw' => [
                        'insights_status' => 'unavailable',
                        'message' => $response['message'] ?? 'AI insights are not available.',
                        'error_code' => $response['error_code'] ?? 'unknown',
                    ],
                ]);

                return [
                    'success' => false,
                    'status' => 'unavailable',
                    'message' => $response['message'] ?? 'AI insights are not available.',
                ];
            }

            $insights = $this->parseInsightsResponse((string) $response['content']);

            if ($insights === null) {
                $prediction->update([
                    'openai_called' => true,
                    'ai_model' => $response['model'] ?? null,
                    'ai_model_used' => $response['model'] ?? null,
                    'ai_response_ms' => $response['response_time_ms'] ?? null,
                    'ai_response_raw' => [
                        'insights_status' => 'unavailable',
                        'message' => 'AI insights could not be parsed.',
                        'error_code' => 'invalid_response',
                    ],
                ]);

                return [
                    'success' => false,
                    'status' => 'unavailable',
                    'message' => 'AI insights could not be parsed.',
                ];
            }

            $prediction->update([
                'openai_called' => true,
                'ai_narrative' => $this->formatInsightsAsText($insights),
                'ai_narrative_generated_at' => now(),
                'ai_model' => $response['model'] ?? null,
                'ai_model_used' => $response['model'] ?? null,
                'ai_tokens_used' => $response['tokens_used'] ?? null,
                'ai_response_ms' => $response['response_time_ms'] ?? null,
                'ai_prompt_hash' => hash('sha256', $messages[0]['content'].$messages[1]['content']),
                'ai_response_raw' => [
                    'insights_status' => 'success',
                    'insights' => $insights,
                    'generated_at' => now()->toIso8601String(),
                ],
            ]);

            return [
                'success' => true,
                'status' => 'success',
                'insights' => $insights,
                'model' => $response['model'] ?? null,
                'tokens_used' => $response['tokens_used'] ?? null,
            ];
        } catch (Throwable $exception) {
            report($exception);

            $prediction->update([
                'ai_response_raw' => [
                    'insights_status' => 'unavailable',
                    'message' => 'AI insights could not be generated.',
                    'error_code' => 'insights_exception',
                ],
            ]);

            return [
                'success' => false,
                'status' => 'unavailable',
                'message' => 'AI insights could not be generated.',
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function generateTestNarrative(array $payload, ?int $userId = null): array
    {
        if (! $this->settings->hasEncrypted('ai.api_key')) {
            return [
                'success' => false,
                'status' => 'unavailable',
                'message' => 'No OpenAI API key is configured.',
            ];
        }

        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => "Create a brief test response using this sample pricing payload. Return valid JSON with all five insight sections. Do not invent numbers.\n\n".json_encode($payload, JSON_PRETTY_PRINT)],
        ], [
            'feature' => 'prediction_narrative_test',
            'user_id' => $userId,
            'max_tokens' => 600,
            'temperature' => 0,
            'timeout' => min($this->settings->getInteger('ai.timeout_seconds', 60) ?? 60, 30),
        ]);

        if (! ($response['success'] ?? false)) {
            return [
                'success' => false,
                'status' => 'unavailable',
                'message' => $response['message'] ?? 'AI insights test failed.',
            ];
        }

        $insights = $this->parseInsightsResponse((string) $response['content']);

        return [
            'success' => true,
            'status' => 'success',
            'insights' => $insights ?? [],
            'content' => $insights ? $this->formatInsightsAsText($insights) : $this->sanitizeText((string) $response['content']),
            'model' => $response['model'] ?? null,
            'tokens_used' => $response['tokens_used'] ?? null,
        ];
    }

    /**
     * @return array<string, string>|null
     */
    public function extractInsights(Prediction $prediction): ?array
    {
        $raw = $prediction->ai_response_raw;

        if (is_array($raw['insights'] ?? null) && $this->isValidInsights($raw['insights'])) {
            return $this->normalizeInsights($raw['insights']);
        }

        if (filled($prediction->ai_narrative) && ($raw['insights_status'] ?? $raw['narrative_status'] ?? null) === 'success') {
            return [
                'market_overview' => '',
                'competition_analysis' => '',
                'discount_review' => '',
                'risk_commentary' => '',
                'strategic_recommendation' => $this->sanitizeText((string) $prediction->ai_narrative),
            ];
        }

        return null;
    }

    public function hasStoredInsights(Prediction $prediction): bool
    {
        $raw = $prediction->ai_response_raw;

        if (($raw['insights_status'] ?? null) === 'success' && is_array($raw['insights'] ?? null)) {
            return $this->isValidInsights($raw['insights']);
        }

        return filled($prediction->ai_narrative)
            && ($raw['insights_status'] ?? $raw['narrative_status'] ?? null) === 'success';
    }

    public function insightsStatus(Prediction $prediction): ?string
    {
        $raw = $prediction->ai_response_raw;

        return $raw['insights_status'] ?? $raw['narrative_status'] ?? null;
    }

    public function insightsMessage(Prediction $prediction): ?string
    {
        $raw = $prediction->ai_response_raw;

        return $raw['message'] ?? null;
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a pharmaceutical tender pricing strategist providing decision-support commentary.

The recommended bid price has already been calculated from historical tender data and market statistics. You must NEVER modify, override, recalculate, or suggest a different price, discount, confidence score, risk level, or scenario prices.

Return ONLY valid JSON with exactly these keys:
- market_overview
- competition_analysis
- discount_review
- risk_commentary
- strategic_recommendation

Rules:
- Use only the supplied data. Do not invent market data, competitors, or guarantees.
- Write in clear business language for procurement and commercial teams.
- Avoid technical terms such as: AI narrative, prompt, backend, engine, model output.
- market_overview: explain market condition, pricing trend, and data quality.
- competition_analysis: explain competition intensity, supplier diversity, and market concentration.
- discount_review: analyze the user discount percentage and its impact on competitiveness and margin. Do not recommend changing the discount.
- risk_commentary: explain main risks, data limitations, and market uncertainties.
- strategic_recommendation: provide business-oriented bidding advice (aggressive, balanced, or conservative positioning) without changing the calculated price.
- Each section: 2-4 sentences, professional and concise.
- No legal, financial, procurement, or winning guarantees.
- All prices in the payload are United States dollars (USD). Always refer to prices with the "USD" suffix (e.g. "1.20 USD"). Never reference SAR, JOD, AED, EUR, or any other local currency.
PROMPT;
    }

    protected function userPrompt(
        Prediction $prediction,
        PredictionContextSnapshot $contextSnapshot,
        PredictionCalculation $calculation,
    ): string {
        $snapshot = $contextSnapshot->snapshot_data;
        $statsRow = $snapshot['selected_stats_row'] ?? [];
        $competitionSummary = $snapshot['competition_summary'] ?? [];
        $tenderContext = $snapshot['tender_context'] ?? [];

        $payload = [
            'tender_information' => [
                'tender_name' => $prediction->tender?->title ?? $tenderContext['title'] ?? null,
                'tender_number' => $prediction->tender?->tender_number ?? $tenderContext['tender_number'] ?? null,
                'country' => $prediction->tender?->country?->name ?? $tenderContext['country_name'] ?? null,
                'quantity' => $prediction->quantity,
                'quantity_unit' => $prediction->quantity_unit,
            ],
            'pricing_currency' => 'USD',
            'pricing_information' => [
                'currency' => 'USD',
                'market_calculated_price' => $prediction->market_calculated_price
                    ?? $calculation->calculation_details['market_calculated_price']
                    ?? $prediction->backend_recommended_price,
                'user_discount_percentage' => $prediction->discount_percentage ?? 0,
                'final_recommended_bid_price' => $prediction->final_recommended_price
                    ?? $prediction->recommended_price
                    ?? $prediction->backend_recommended_price,
            ],
            'confidence' => [
                'data_confidence' => $prediction->confidence_score,
                'risk_level' => $prediction->risk_level,
            ],
            'market_statistics' => [
                'award_count' => $calculation->historical_award_count ?? $statsRow['award_count'] ?? null,
                'weighted_average' => $calculation->weighted_average_price ?? $statsRow['weighted_avg_unit_price'] ?? null,
                'median' => $calculation->median_price ?? $statsRow['median_unit_price'] ?? null,
                'last_award_price' => $calculation->last_winning_price ?? $statsRow['last_unit_price'] ?? null,
                'trend' => [
                    'direction' => $calculation->price_trend ?? $statsRow['trend_direction'] ?? null,
                    'percentage' => $calculation->trend_pct ?? $statsRow['trend_pct'] ?? null,
                ],
                'distinct_winners' => $competitionSummary['distinct_winners'] ?? null,
                'competition_level' => $calculation->competition_level ?? null,
            ],
            'scenario_information' => $prediction->predictionScenarios
                ->sortBy(fn ($scenario) => match ($scenario->scenario_name) {
                    'aggressive' => 1,
                    'balanced' => 2,
                    'conservative' => 3,
                    default => 99,
                })
                ->map(fn ($scenario) => [
                    'name' => $scenario->scenario_name,
                    'recommended_price' => $scenario->recommended_price,
                    'currency' => 'USD',
                    'risk_level' => $scenario->risk_level,
                    'is_recommended' => $scenario->is_recommended,
                ])
                ->values()
                ->all(),
        ];

        return "Generate AI strategic insights for this completed price recommendation. Return JSON only.\n\n".json_encode($payload, JSON_PRETTY_PRINT);
    }

    /**
     * @return array<string, string>|null
     */
    protected function parseInsightsResponse(string $content): ?array
    {
        $content = trim($content);

        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
            $content = preg_replace('/\s*```$/', '', $content) ?? $content;
        }

        $decoded = json_decode(trim($content), true);

        if (! is_array($decoded) || ! $this->isValidInsights($decoded)) {
            return null;
        }

        return $this->normalizeInsights($decoded);
    }

    /**
     * @param  array<string, mixed>  $insights
     */
    protected function isValidInsights(array $insights): bool
    {
        foreach (array_keys(self::INSIGHT_SECTIONS) as $key) {
            if (! filled($insights[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $insights
     * @return array<string, string>
     */
    protected function normalizeInsights(array $insights): array
    {
        $normalized = [];

        foreach (self::INSIGHT_SECTIONS as $key => $label) {
            $normalized[$key] = $this->sanitizeText((string) ($insights[$key] ?? ''));
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $insights
     */
    protected function formatInsightsAsText(array $insights): string
    {
        $parts = [];

        foreach (self::INSIGHT_SECTIONS as $key => $label) {
            if (filled($insights[$key] ?? null)) {
                $parts[] = $label.': '.$insights[$key];
            }
        }

        return Str::limit(implode("\n\n", $parts), 6000, '');
    }

    protected function sanitizeText(string $content): string
    {
        $content = trim(strip_tags($content));
        $content = preg_replace('/[^\P{C}\t\n\r]+/u', '', $content) ?? '';

        return Str::limit($content, 1800, '');
    }

    /**
     * @return array<string, mixed>
     */
    protected function markSkipped(Prediction $prediction, string $message): array
    {
        $existingStatus = $prediction->ai_response_raw['insights_status'] ?? $prediction->ai_response_raw['narrative_status'] ?? null;

        if ($existingStatus === 'success' && $this->hasStoredInsights($prediction)) {
            return [
                'success' => true,
                'status' => 'cached',
                'insights' => $this->extractInsights($prediction),
                'message' => 'Existing AI insights preserved.',
            ];
        }

        $prediction->update([
            'ai_response_raw' => [
                'insights_status' => 'skipped',
                'message' => $message,
            ],
        ]);

        return [
            'success' => false,
            'status' => 'skipped',
            'message' => $message,
        ];
    }
}
