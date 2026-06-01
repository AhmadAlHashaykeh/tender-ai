<?php

namespace Tests\Unit;

use App\Enums\ImportRowValidationStatus;
use App\Services\Import\ImportValidatorService;
use Tests\TestCase;

class ImportValidatorServiceTest extends TestCase
{
    private ImportValidatorService $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ImportValidatorService;
    }

    public function test_formula_equals_in_price_usd_normalizes_to_null_and_marks_invalid(): void
    {
        $result = $this->validator->validate($this->minimalCanonical(['price_usd' => '=']));

        $this->assertNull($result['normalized_data']['price_usd']);
        $this->assertEquals(ImportRowValidationStatus::Invalid->value, $result['validation_status']);
        $this->assertStringContainsString('Price USD', $result['error_message'] ?? '');
    }

    public function test_formula_string_like_round_does_not_crash_and_normalizes_price_to_null(): void
    {
        $result = $this->validator->validate($this->minimalCanonical(['price_usd' => '=ROUND(F2,4)']));

        $this->assertNull($result['normalized_data']['price_usd']);
        $this->assertEquals(ImportRowValidationStatus::Invalid->value, $result['validation_status']);
    }

    public function test_formula_in_inn_field_does_not_count_as_drug_identity(): void
    {
        $result = $this->validator->validate($this->minimalCanonical([
            'code' => null,
            'inn' => '=PROPER(LOWER(H2))',
            'product_name' => null,
        ]));

        $this->assertNull($result['normalized_data']['inn']);
        $this->assertEquals(ImportRowValidationStatus::Invalid->value, $result['validation_status']);
        $this->assertStringContainsString('drug identity', strtolower($result['error_message'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minimalCanonical(array $overrides = []): array
    {
        return array_merge([
            'code' => 'D001',
            'inn' => 'Paracetamol',
            'product_name' => 'Paracetamol 500mg',
            'country' => 'Saudi Arabia',
            'tender_number' => 'T-2024-001',
            'awarded_price' => '420',
            'price_usd' => '425',
            'winner' => 'PharmaCorp',
            'company_name' => 'PharmaCorp International',
            'version' => 'v1',
            'year' => '2024',
            'qty' => '1000',
            'tender_value' => '425000',
        ], $overrides);
    }
}
