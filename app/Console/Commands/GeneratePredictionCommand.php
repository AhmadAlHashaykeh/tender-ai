<?php

namespace App\Console\Commands;

use App\Models\Tender;
use App\Models\User;
use App\Services\Prediction\PredictionOrchestratorService;
use App\Services\Prediction\TenderRecommendationContextService;
use Illuminate\Console\Command;

class GeneratePredictionCommand extends Command
{
    protected $signature = 'predictions:generate
                            {--drug= : Standardized drug ID}
                            {--tender= : Tender ID (required)}
                            {--quantity= : Requested quantity (required)}
                            {--discount=0 : Bid discount percentage (0-100)}
                            {--user= : User ID (defaults to first user)}';

    protected $description = 'Generate a backend-only price prediction from CLI';

    public function handle(
        PredictionOrchestratorService $orchestrator,
        TenderRecommendationContextService $tenderContext,
    ): int {
        $drugId = $this->option('drug');
        $tenderId = $this->option('tender');
        $quantity = $this->option('quantity');

        if (! $drugId || ! $tenderId || $quantity === null) {
            $this->error('Options --drug, --tender, and --quantity are all required.');

            return self::FAILURE;
        }

        $countryId = $tenderContext->resolveCountryId((int) $tenderId);
        if ($countryId === null) {
            $this->error('Tender not found or has no country.');

            return self::FAILURE;
        }

        $userId = $this->option('user');
        $user = $userId
            ? User::query()->find($userId)
            : User::query()->first();

        if (! $user) {
            $this->error('No user found. Seed users or pass --user=ID.');

            return self::FAILURE;
        }

        $tender = Tender::query()->find($tenderId);
        $this->info('Tender: '.($tender?->title ?? $tender?->tender_number ?? $tenderId));

        $prediction = $orchestrator->run($user, [
            'standardized_drug_id' => (int) $drugId,
            'country_id' => $countryId,
            'tender_id' => (int) $tenderId,
            'quantity' => (float) $quantity,
            'quantity_unit' => 'units',
            'discount_percentage' => (float) ($this->option('discount') ?? 0),
            'recommendation_mode' => 'calculation',
        ]);

        if ($prediction->status === 'failed') {
            $this->error($prediction->rationale ?? 'Prediction failed.');

            return self::FAILURE;
        }

        $this->info('Prediction completed: '.$prediction->uuid);
        $this->table(
            ['Field', 'Value'],
            [
                ['Tender ID', $prediction->tender_id],
                ['Recommended price', $prediction->backend_recommended_price],
                ['Confidence', $prediction->confidence_score],
                ['Risk', $prediction->risk_level],
                ['Stats version', $prediction->stats_version],
            ],
        );

        return self::SUCCESS;
    }
}
