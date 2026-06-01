<?php

namespace App\Services\Prediction;

use App\Enums\PredictionSource;
use App\Enums\PredictionStatus;
use App\Models\Prediction;
use App\Models\PredictionCalculation;
use App\Models\PredictionScenario;
use App\Models\StandardizedDrug;
use App\Models\User;
use App\Services\AI\PredictionNarrativeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class PredictionOrchestratorService
{
    public function __construct(
        protected PredictionCalculationService $calculationService,
        protected PredictionContextBuilderService $contextBuilder,
        protected PredictionScenarioService $scenarioService,
        protected PredictionNarrativeService $narrativeService,
        protected TenderRecommendationContextService $tenderContext,
    ) {}

    /**
     * @param  array{standardized_drug_id: int, country_id: int, quantity: float, quantity_unit?: ?string, tender_id: int, discount_percentage?: float|int|string}  $input
     */
    public function run(User $user, array $input): Prediction
    {
        $this->validateInput($input);

        $discountPercentage = (float) ($input['discount_percentage'] ?? 0);

        $countryId = (int) ($input['country_id'] ?? $this->tenderContext->resolveCountryId((int) $input['tender_id']));

        $prediction = Prediction::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'tender_id' => $input['tender_id'],
            'standardized_drug_id' => $input['standardized_drug_id'],
            'quantity' => $input['quantity'],
            'quantity_unit' => $input['quantity_unit'] ?? null,
            'discount_percentage' => $discountPercentage,
            'status' => PredictionStatus::Processing->value,
            'source' => PredictionSource::BackendOnly->value,
            'recommendation_mode' => 'calculation',
            'openai_called' => false,
        ]);

        $startedAt = microtime(true);

        try {
            $completedPrediction = DB::transaction(function () use ($prediction, $input, $countryId, $startedAt, $discountPercentage) {
                $result = $this->calculationService->calculate(
                    $input['standardized_drug_id'],
                    $countryId,
                    (float) $input['quantity'],
                    (int) $input['tender_id'],
                    $discountPercentage,
                );

                if (! ($result['success'] ?? false)) {
                    $prediction->update([
                        'status' => PredictionStatus::Failed->value,
                        'rationale' => $result['message'] ?? 'Prediction failed.',
                        'completed_at' => now(),
                        'processing_time_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                    ]);

                    return $prediction->fresh();
                }

                $statistic = $result['statistic'];
                $scenarios = $this->scenarioService->generate(
                    $result['final_recommended_price'],
                    $result['risk_level'],
                    $result['confidence_score'],
                    $statistic,
                    $result['competition'],
                    $discountPercentage,
                );

                $context = $this->contextBuilder->buildAndStore(
                    $prediction,
                    $statistic,
                    $result['fallback_level'],
                    $countryId,
                    $result,
                    (float) $input['quantity'],
                    (int) $input['tender_id'],
                );

                PredictionCalculation::query()->create(array_merge(
                    ['prediction_id' => $prediction->id],
                    $result['calculation_record'],
                ));

                foreach ($scenarios as $scenario) {
                    PredictionScenario::query()->create(array_merge(
                        ['prediction_id' => $prediction->id],
                        $scenario,
                    ));
                }

                $balancedScenario = collect($scenarios)->firstWhere('scenario_name', 'balanced');
                $winProbability = $balancedScenario['win_probability'] ?? null;

                $prediction->update([
                    'market_calculated_price' => $result['market_calculated_price'],
                    'final_recommended_price' => $result['final_recommended_price'],
                    'recommended_price' => $result['final_recommended_price'],
                    'backend_recommended_price' => $result['final_recommended_price'],
                    'currency_id' => $result['currency_id'],
                    'win_probability' => $winProbability,
                    'risk_level' => $result['risk_level']->value,
                    'confidence_score' => $result['confidence_score'],
                    'calculation_model_version' => $result['calculation_model_version'],
                    'stats_version' => $result['stats_version'],
                    'context_snapshot' => $context['snapshot'],
                    'rationale' => $this->buildRationale($input, $result, $context['snapshot']),
                    'status' => PredictionStatus::Completed->value,
                    'completed_at' => now(),
                    'processing_time_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                ]);

                return $prediction->fresh([
                    'predictionCalculations',
                    'predictionScenarios',
                    'contextSnapshots',
                    'standardizedDrug',
                    'currency',
                    'tender.country',
                ]);
            });

            if ($completedPrediction->status === PredictionStatus::Completed->value) {
                $this->narrativeService->generateForPrediction($completedPrediction, [
                    'dry_run' => false,
                ]);
            }

            return $completedPrediction->fresh([
                'predictionCalculations',
                'predictionScenarios',
                'contextSnapshots',
                'standardizedDrug',
                'currency',
                'tender.country',
            ]);
        } catch (Throwable $exception) {
            $prediction->update([
                'status' => PredictionStatus::Failed->value,
                'rationale' => 'Prediction failed: '.$exception->getMessage(),
                'completed_at' => now(),
                'processing_time_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);

            report($exception);

            return $prediction->fresh();
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function validateInput(array $input): void
    {
        if (empty($input['tender_id'])) {
            throw new InvalidArgumentException('A tender must be selected to generate a recommendation.');
        }

        if (! isset($input['quantity']) || ! is_numeric($input['quantity']) || (float) $input['quantity'] <= 0) {
            throw new InvalidArgumentException('Please enter the required tender quantity (must be greater than zero).');
        }

        if (empty($input['standardized_drug_id'])) {
            throw new InvalidArgumentException('A drug or product must be selected.');
        }

        if (! isset($input['discount_percentage']) || ! is_numeric($input['discount_percentage'])) {
            throw new InvalidArgumentException('Please enter the bid discount percentage.');
        }

        $discount = (float) $input['discount_percentage'];
        if ($discount < 0 || $discount > 100) {
            throw new InvalidArgumentException('Bid discount percentage must be between 0 and 100.');
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $snapshot
     */
    protected function buildRationale(array $input, array $result, array $snapshot): string
    {
        $drug = StandardizedDrug::query()->find($input['standardized_drug_id']);
        $drugName = $drug?->display_name ?? 'selected drug';
        $scopeLabel = $result['fallback_level']->label();
        $awards = $result['calculation_record']['historical_award_count'] ?? 0;
        $marketPrice = number_format((float) $result['market_calculated_price'], 2);
        $discount = number_format((float) $result['discount_percentage'], 2);
        $finalPrice = number_format((float) $result['final_recommended_price'], 2);
        $tenderLabel = $snapshot['tender_context']['title']
            ?? $snapshot['tender_context']['tender_number']
            ?? null;

        $lines = [
            "Market calculated price for {$drugName} is \${$marketPrice} per unit.",
        ];

        if ((float) $result['discount_percentage'] > 0) {
            $lines[] = "A {$discount}% bid discount was applied, yielding a final recommended bid of \${$finalPrice} per unit.";
        } else {
            $lines[] = "No bid discount was applied. Final recommended bid price is \${$finalPrice} per unit.";
        }

        if ($tenderLabel) {
            $lines[] = "Prepared for tender: {$tenderLabel}.";
        }

        $lines[] = "Based on {$awards} historical awards using {$scopeLabel}.";
        $lines[] = 'Data confidence: '.($result['confidence_score'] ?? 0).'/100.';
        $lines[] = 'Risk level: '.($result['risk_level']->value ?? 'unknown').'.';

        if (($snapshot['outlier_summary']['count'] ?? 0) > 0) {
            $lines[] = 'Note: '.$snapshot['outlier_summary']['count'].' outlier price(s) flagged in historical data.';
        }

        if ($result['fallback_level']->value !== 'country') {
            $lines[] = $result['fallback_level']->description();
        }

        $tenderAwards = count($snapshot['tender_specific_awards'] ?? []);
        if ($tenderAwards > 0) {
            $lines[] = "Includes {$tenderAwards} tender-specific historical award(s) in context.";
        }

        return implode(' ', $lines);
    }
}
