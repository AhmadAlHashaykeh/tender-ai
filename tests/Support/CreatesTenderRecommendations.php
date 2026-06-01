<?php

namespace Tests\Support;

use App\Models\Tender;

trait CreatesTenderRecommendations
{
    protected Tender $testTender;

    protected function createTestTender(array $overrides = []): Tender
    {
        return Tender::query()->create(array_merge([
            'tender_number' => 'TEST-'.uniqid(),
            'country_id' => $this->country->id,
            'year' => 2024,
            'title' => 'Test Tender',
            'status' => 'active',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function recommendationPayload(array $overrides = []): array
    {
        if (! isset($this->testTender)) {
            $this->testTender = $this->createTestTender();
        }

        return array_merge([
            'tender_id' => $this->testTender->id,
            'standardized_drug_id' => $this->drug->id,
            'country_id' => $this->country->id,
            'quantity' => 5000,
            'quantity_unit' => 'units',
            'discount_percentage' => 0,
            'recommendation_mode' => 'calculation',
        ], $overrides);
    }
}
