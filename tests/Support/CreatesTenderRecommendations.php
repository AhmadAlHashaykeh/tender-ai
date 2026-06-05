<?php

namespace Tests\Support;

use App\Models\Tender;
use App\Models\TenderItem;
use App\Services\Tender\TenderGroupKeyService;

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

        $this->ensureDrugExistsInTestTenderGroup();

        $groupKey = app(TenderGroupKeyService::class)->deriveFromTender($this->testTender);

        return array_merge([
            'tender_group_key' => $groupKey,
            'tender_id' => $this->testTender->id,
            'standardized_drug_id' => $this->drug->id,
            'country_id' => $this->country->id,
            'quantity' => 5000,
            'quantity_unit' => 'units',
            'discount_percentage' => 0,
            'recommendation_mode' => 'calculation',
        ], $overrides);
    }

    protected function ensureDrugExistsInTestTenderGroup(): void
    {
        if (! isset($this->drug, $this->testTender)) {
            return;
        }

        $hasItem = TenderItem::query()
            ->where('tender_id', $this->testTender->id)
            ->where('standardized_drug_id', $this->drug->id)
            ->exists();

        if ($hasItem) {
            return;
        }

        TenderItem::query()->create([
            'tender_id' => $this->testTender->id,
            'standardized_drug_id' => $this->drug->id,
        ]);
    }
}
