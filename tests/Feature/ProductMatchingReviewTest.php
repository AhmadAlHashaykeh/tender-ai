<?php

namespace Tests\Feature;

use App\Enums\ImportRowValidationStatus;
use App\Enums\StandardizationStatus;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\StandardizedDrug;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\StandardizationReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductMatchingReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->seed([
            RegionSeeder::class,
            CurrencySeeder::class,
            CountrySeeder::class,
            StandardizationReferenceSeeder::class,
        ]);
    }

    public function test_review_queue_renders_card_layout_with_summary(): void
    {
        $user = User::factory()->create();
        $this->makeReviewRow(['confidence_score' => 92]);

        $this->actingAs($user)
            ->get(route('standardization.index'))
            ->assertOk()
            ->assertSee('Product Matching')
            ->assertSee('High Confidence')
            ->assertSee('Bulk Actions')
            ->assertSee('Original Product')
            ->assertSee('Suggested Match');
    }

    public function test_bulk_approve_processes_selected_rows(): void
    {
        $user = User::factory()->create();
        $rowA = $this->makeReviewRow(['row_number' => 1]);
        $rowB = $this->makeReviewRow(['row_number' => 2]);

        $this->actingAs($user)
            ->postJson(route('standardization.bulk-action'), [
                'action' => 'approve',
                'row_ids' => [$rowA->id, $rowB->id],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals(StandardizationStatus::Approved->value, $rowA->fresh()->standardization_status);
        $this->assertEquals(StandardizationStatus::Approved->value, $rowB->fresh()->standardization_status);
    }

    public function test_bulk_reject_processes_selected_rows(): void
    {
        $user = User::factory()->create();
        $row = $this->makeReviewRow();

        $this->actingAs($user)
            ->postJson(route('standardization.bulk-action'), [
                'action' => 'reject',
                'row_ids' => [$row->id],
            ])
            ->assertOk();

        $this->assertEquals(StandardizationStatus::Rejected->value, $row->fresh()->standardization_status);
    }

    public function test_manual_edit_updates_drug_match(): void
    {
        $user = User::factory()->create();
        $row = $this->makeReviewRow();
        $drug = StandardizedDrug::where('code', 'D002')->first()
            ?? StandardizedDrug::query()->first();

        $this->actingAs($user)
            ->putJson(route('standardization.edit-match', $row), [
                'entity' => 'drug',
                'standardized_drug_id' => $drug->id,
            ])
            ->assertOk();

        $row->refresh();
        $this->assertEquals($drug->id, $row->standardized_drug_id);
        $this->assertEquals(100.0, (float) $row->drug_confidence);
    }

    public function test_product_search_returns_results(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('standardization.search-products', ['q' => 'Paracetamol']))
            ->assertOk()
            ->assertJsonStructure(['results']);
    }

    public function test_pagination_limits_results(): void
    {
        $user = User::factory()->create();

        for ($i = 1; $i <= 30; $i++) {
            $this->makeReviewRow(['row_number' => $i]);
        }

        $response = $this->actingAs($user)
            ->get(route('standardization.index', ['per_page' => 25]));

        $response->assertOk();
        $this->assertEquals(25, substr_count($response->getContent(), 'data-review-card'));
    }

    public function test_confidence_filter_works(): void
    {
        $user = User::factory()->create();
        $this->makeReviewRow([
            'confidence_score' => 96,
            'raw_product_name' => 'HighConf Product',
            'normalized_data' => $this->reviewNormalizedData('HighConf Product'),
        ]);
        $this->makeReviewRow([
            'confidence_score' => 55,
            'raw_product_name' => 'LowConf Product',
            'normalized_data' => $this->reviewNormalizedData('LowConf Product'),
        ]);

        $this->actingAs($user)
            ->get(route('standardization.index', ['confidence_min' => 90]))
            ->assertOk()
            ->assertSee('HighConf Product')
            ->assertDontSee('LowConf Product');
    }

    /**
     * @return array<string, mixed>
     */
    protected function reviewNormalizedData(string $productName): array
    {
        return [
            'price_usd' => 100.0,
            'year' => 2024,
            'country_confidence' => 95,
            'standardization' => [
                'review_items' => [[
                    'entity' => 'drug',
                    'original' => $productName,
                    'suggested' => $productName.' Tab',
                    'confidence' => 85,
                    'reason' => 'Fuzzy product match',
                ]],
                'drug' => ['display_name' => $productName.' Tab'],
                'company' => ['canonical_name' => 'PharmaCorp International'],
                'country' => ['canonical_name' => 'United Arab Emirates'],
            ],
        ];
    }

    protected function makeReviewRow(array $overrides = []): ImportRow
    {
        $batch = ImportBatch::create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'test.csv',
            'original_filename' => 'test.csv',
            'file_path' => 'imports/test.csv',
            'file_hash' => hash('sha256', uniqid('', true)),
            'row_count' => 1,
            'status' => 'completed',
            'source_type' => 'csv',
        ]);

        return ImportRow::create(array_merge([
            'import_batch_id' => $batch->id,
            'row_number' => 1,
            'row_hash' => hash('sha256', uniqid('', true)),
            'raw_code' => 'D001',
            'raw_inn' => 'Paracetamol',
            'raw_product_name' => 'Paracetamol 500mg',
            'raw_country' => 'UAE',
            'raw_tender_number' => 'T-2024-002',
            'raw_awarded_price' => '100',
            'raw_price_usd' => '100',
            'raw_winner' => 'PharmaCorp',
            'raw_company_name' => 'PharmaCorp International',
            'raw_version' => 'v1',
            'raw_year' => '2024',
            'raw_qty' => '10',
            'raw_tender_value' => '1000',
            'validation_status' => ImportRowValidationStatus::Valid->value,
            'standardization_status' => StandardizationStatus::ReviewRequired->value,
            'confidence_score' => 88,
            'drug_confidence' => 85,
            'company_confidence' => 90,
            'tender_confidence' => 88,
            'raw_data' => [],
            'normalized_data' => [
                'price_usd' => 100.0,
                'year' => 2024,
                'country_confidence' => 95,
                'standardization' => [
                    'review_items' => [[
                        'entity' => 'drug',
                        'original' => 'Paracetamol 500mg',
                        'suggested' => 'Paracetamol 500mg Tab',
                        'confidence' => 85,
                        'reason' => 'Fuzzy product match',
                    ]],
                    'drug' => ['display_name' => 'Paracetamol 500mg Tab'],
                    'company' => ['canonical_name' => 'PharmaCorp International'],
                    'country' => ['canonical_name' => 'United Arab Emirates'],
                ],
            ],
        ], $overrides));
    }
}
