<?php

namespace Tests\Feature;

use App\Enums\ImportRowValidationStatus;
use App\Enums\StandardizationStatus;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\StandardizationLog;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\StandardizationReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApproveAllReviewTest extends TestCase
{
    use RefreshDatabase;

    protected ImportBatch $batch;

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

        $this->batch = ImportBatch::create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'bulk-test.csv',
            'original_filename' => 'bulk-test.csv',
            'file_path' => 'imports/bulk-test.csv',
            'file_hash' => hash('sha256', 'bulk-test'),
            'row_count' => 0,
            'status' => 'completed',
            'source_type' => 'csv',
        ]);
    }

    public function test_approve_all_review_items_for_batch(): void
    {
        $user = User::factory()->create();

        $this->createRows($this->batch->id, 150, StandardizationStatus::ReviewRequired->value);
        $this->createRows($this->batch->id, 5, StandardizationStatus::Rejected->value);
        $this->createRows($this->batch->id, 3, StandardizationStatus::Skipped->value);

        $otherBatch = ImportBatch::create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'other.csv',
            'original_filename' => 'other.csv',
            'file_path' => 'imports/other.csv',
            'file_hash' => hash('sha256', 'other'),
            'row_count' => 0,
            'status' => 'completed',
            'source_type' => 'csv',
        ]);
        $this->createRows($otherBatch->id, 10, StandardizationStatus::ReviewRequired->value);

        $response = $this->actingAs($user)
            ->post(route('standardization.approve-all-review', $this->batch));

        $response->assertRedirect(route('imports.show', $this->batch));
        $response->assertSessionHas('success', 'Approved 150 review items successfully.');

        $this->assertEquals(
            150,
            ImportRow::query()
                ->where('import_batch_id', $this->batch->id)
                ->where('standardization_status', StandardizationStatus::Approved->value)
                ->count()
        );

        $this->assertEquals(5, ImportRow::query()->where('import_batch_id', $this->batch->id)->where('standardization_status', StandardizationStatus::Rejected->value)->count());
        $this->assertEquals(3, ImportRow::query()->where('import_batch_id', $this->batch->id)->where('standardization_status', StandardizationStatus::Skipped->value)->count());
        $this->assertEquals(10, ImportRow::query()->where('import_batch_id', $otherBatch->id)->where('standardization_status', StandardizationStatus::ReviewRequired->value)->count());

        $log = StandardizationLog::query()->where('action', 'bulk_approve_all')->first();
        $this->assertNotNull($log);
        $this->assertEquals($user->id, $log->performed_by);
        $this->assertEquals('import_batch', $log->entity_type);
        $this->assertEquals($this->batch->id, $log->entity_id);
        $this->assertEquals(150, $log->new_values['approved_count']);
        $this->assertEquals($this->batch->id, $log->new_values['batch_id']);
    }

    public function test_approve_all_button_visible_on_batch_review_queue(): void
    {
        $user = User::factory()->create();
        $this->createRows($this->batch->id, 2, StandardizationStatus::ReviewRequired->value);

        $this->actingAs($user)
            ->get(route('standardization.index', [
                'batch' => $this->batch->id,
                'status' => 'review_required',
            ]))
            ->assertOk()
            ->assertSee('Approve All Review Items')
            ->assertSee('Approve All Review Items (2)');
    }

    public function test_approve_all_updates_batch_metadata_counts(): void
    {
        $user = User::factory()->create();
        $this->createRows($this->batch->id, 12, StandardizationStatus::ReviewRequired->value);
        $this->createRows($this->batch->id, 4, StandardizationStatus::AutoApproved->value);

        $this->actingAs($user)
            ->post(route('standardization.approve-all-review', $this->batch));

        $this->batch->refresh();

        $this->assertEquals(0, (int) ($this->batch->metadata['standardization_review_rows'] ?? -1));
        $this->assertEquals(4, (int) ($this->batch->metadata['auto_approved_rows'] ?? 0));
    }

    protected function createRows(int $batchId, int $count, string $status): void
    {
        for ($i = 1; $i <= $count; $i++) {
            ImportRow::create([
                'import_batch_id' => $batchId,
                'row_number' => $i,
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
                'standardization_status' => $status,
                'confidence_score' => 88,
                'drug_confidence' => 85,
                'company_confidence' => 90,
                'tender_confidence' => 88,
                'raw_data' => [],
                'normalized_data' => [
                    'price_usd' => 100.0,
                    'year' => 2024,
                ],
            ]);
        }
    }
}
