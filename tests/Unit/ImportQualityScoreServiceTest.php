<?php

namespace Tests\Unit;

use App\Enums\ImportRowValidationStatus;
use App\Enums\StandardizationStatus;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use App\Services\Import\ImportPipelineService;
use App\Services\Import\ImportQualityScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportQualityScoreServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_success_never_exceeds_one_hundred_percent(): void
    {
        $batch = $this->createBatchWithRows([
            ['validation_status' => ImportRowValidationStatus::Valid->value, 'confidence_score' => 100],
            ['validation_status' => ImportRowValidationStatus::Valid->value, 'confidence_score' => 100],
            ['validation_status' => ImportRowValidationStatus::Warning->value, 'confidence_score' => 92],
            ['validation_status' => ImportRowValidationStatus::Invalid->value, 'confidence_score' => 0],
        ]);

        $result = app(ImportQualityScoreService::class)->calculateForBatch($batch);

        $this->assertLessThanOrEqual(100, $result['breakdown']['validation_success']);
        $this->assertSame(75.0, $result['breakdown']['validation_success']);
        $this->assertLessThanOrEqual(100, $result['score']);
    }

    public function test_quality_score_reflects_warnings_invalid_and_missing_standardization(): void
    {
        $batch = $this->createBatchWithRows([
            ['validation_status' => ImportRowValidationStatus::Valid->value, 'confidence_score' => 100],
            ['validation_status' => ImportRowValidationStatus::Warning->value, 'confidence_score' => 84],
            ['validation_status' => ImportRowValidationStatus::Invalid->value, 'confidence_score' => 0],
        ], mappingConfidence: 100);

        $result = app(ImportQualityScoreService::class)->calculateForBatch($batch);

        $this->assertSame(0.0, $result['breakdown']['standardization_confidence']);
        $this->assertSame(66.67, $result['breakdown']['validation_success']);
        $this->assertSame(66.67, $result['breakdown']['warning_score']);
        $this->assertSame(66.67, $result['breakdown']['missing_data_score']);
        $this->assertLessThan(90, $result['score']);
        $this->assertNotSame('Excellent', $result['rating']);
    }

    public function test_pipeline_marks_standardization_as_current_when_rows_are_pending(): void
    {
        $batch = $this->createBatchWithRows([
            ['validation_status' => ImportRowValidationStatus::Valid->value, 'confidence_score' => 100],
        ], status: 'completed');

        $pipeline = app(ImportPipelineService::class)->state($batch);

        $this->assertSame('standardization', $pipeline['current_stage']);
        $this->assertTrue($pipeline['can_run_standardization']);
        $this->assertFalse($pipeline['can_materialize']);
        $this->assertFalse($pipeline['is_standardization_running']);
        $this->assertSame('current', collect($pipeline['steps'])->firstWhere('key', 'standardization')['status']);
    }

    /**
     * @param  list<array{validation_status: string, confidence_score: float|int, standardization_status?: string}>  $rows
     */
    protected function createBatchWithRows(
        array $rows,
        string $status = 'completed_with_errors',
        float $mappingConfidence = 100,
    ): ImportBatch {
        $user = User::factory()->create();

        $batch = ImportBatch::query()->create([
            'uuid' => (string) str()->uuid(),
            'filename' => 'test.csv',
            'original_filename' => 'test.csv',
            'uploaded_by' => $user->id,
            'row_count' => count($rows),
            'status' => $status,
            'metadata' => [
                'mapping_confidence' => $mappingConfidence,
            ],
        ]);

        foreach ($rows as $index => $row) {
            ImportRow::query()->create([
                'import_batch_id' => $batch->id,
                'row_number' => $index + 1,
                'row_hash' => hash('sha256', (string) $index),
                'raw_data' => [],
                'validation_status' => $row['validation_status'],
                'standardization_status' => $row['standardization_status'] ?? StandardizationStatus::Pending->value,
                'confidence_score' => $row['confidence_score'],
                'normalized_data' => ['price_usd' => 10.0],
            ]);
        }

        return $batch->fresh();
    }
}
