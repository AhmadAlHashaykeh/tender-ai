<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportRowValidationStatus;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_creates_import_rows_and_detects_duplicates(): void
    {
        $user = User::factory()->create();

        $path = base_path('tests/fixtures/sample_tender_import.csv');
        $file = new UploadedFile($path, 'sample_tender_import.csv', 'text/csv', null, true);

        $response = $this->actingAs($user)->post(route('uploads.store'), [
            'file' => $file,
        ]);

        $batch = ImportBatch::query()->latest()->first();

        $this->assertNotNull($batch);
        $response->assertRedirect(route('imports.mapping', $batch));
        $this->assertEquals(ImportBatchStatus::AwaitingMapping->value, $batch->status);

        $mapping = $batch->metadata['mapped_headers'] ?? [];
        $this->confirmMapping($user, $batch, $mapping);

        $batch = $batch->fresh();
        $this->assertEquals(5, $batch->row_count);
        $this->assertContains($batch->status, [
            ImportBatchStatus::CompletedWithErrors->value,
            ImportBatchStatus::Completed->value,
        ]);

        $invalid = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->where('validation_status', ImportRowValidationStatus::Invalid->value)
            ->count();

        $this->assertGreaterThanOrEqual(1, $invalid);

        $duplicates = ImportRow::query()
            ->where('import_batch_id', $batch->id)
            ->where('validation_status', ImportRowValidationStatus::Duplicate->value)
            ->count();

        $this->assertGreaterThanOrEqual(1, $duplicates);
    }

    public function test_reupload_detects_cross_batch_duplicate(): void
    {
        $user = User::factory()->create();
        $path = base_path('tests/fixtures/sample_tender_import.csv');

        $this->uploadAndConfirm($user, $path);
        $this->uploadAndConfirm($user, $path);

        $this->assertEquals(2, ImportBatch::query()->count());

        $secondBatch = ImportBatch::query()->orderByDesc('id')->first();

        $crossDuplicates = ImportRow::query()
            ->where('import_batch_id', $secondBatch->id)
            ->where('validation_status', ImportRowValidationStatus::Duplicate->value)
            ->count();

        $this->assertGreaterThan(0, $crossDuplicates);
    }

    public function test_import_row_with_very_long_raw_inn_stores_successfully(): void
    {
        $user = User::factory()->create();
        $longInn = str_repeat('AMINO ACIDS (INFANT) FOR PARENTERAL NUTRITION 500ML BOTTLE', 10);

        $this->assertGreaterThan(255, strlen($longInn));

        $batch = $this->uploadCsv($user, $this->csvRow(
            inn: $longInn,
            productName: 'Parenteral Nutrition',
        ));

        $row = ImportRow::query()->where('import_batch_id', $batch->id)->first();

        $this->assertNotNull($row);
        $this->assertSame($longInn, $row->raw_inn);
        $this->assertSame($longInn, $row->raw_data['canonical']['inn'] ?? null);
    }

    public function test_formula_equals_in_price_usd_becomes_null_and_row_is_invalid(): void
    {
        $user = User::factory()->create();

        $batch = $this->uploadCsv($user, $this->csvRow(priceUsd: '='));

        $row = ImportRow::query()->where('import_batch_id', $batch->id)->first();

        $this->assertNotNull($row);
        $this->assertSame('=', $row->raw_price_usd);
        $this->assertNull($row->normalized_data['price_usd']);
        $this->assertEquals(ImportRowValidationStatus::Invalid->value, $row->validation_status);
        $this->assertEquals(ImportBatchStatus::CompletedWithErrors->value, $batch->fresh()->status);
    }

    public function test_formula_string_in_price_usd_does_not_crash_and_preserves_raw_data(): void
    {
        $user = User::factory()->create();
        $formula = '=ROUND(F2,4)';

        $batch = $this->uploadCsv($user, $this->csvRow(priceUsd: $formula));

        $row = ImportRow::query()->where('import_batch_id', $batch->id)->first();

        $this->assertNotNull($row);
        $this->assertSame($formula, $row->raw_price_usd);
        $this->assertSame($formula, $row->raw_data['canonical']['price_usd'] ?? null);
        $this->assertNull($row->normalized_data['price_usd']);
        $this->assertEquals(ImportRowValidationStatus::Invalid->value, $row->validation_status);
    }

    public function test_upload_completes_when_invalid_rows_exist(): void
    {
        $user = User::factory()->create();

        $batch = $this->uploadCsv($user, $this->csvRow(
            country: '',
            priceUsd: '=L2*G2',
        ));

        $batch = $batch->fresh();

        $this->assertEquals(1, $batch->row_count);
        $this->assertEquals(ImportBatchStatus::CompletedWithErrors->value, $batch->status);
        $this->assertGreaterThanOrEqual(1, $batch->error_count);
    }

    public function test_extra_columns_are_preserved_in_raw_data(): void
    {
        $user = User::factory()->create();

        $header = 'Code,INN,Product Name,Country,Tender #,Awarded price,Price USD,Winner,Company Name,Version,Year,Qty,Tender Value,Notes,Buyer';
        $row = $this->csvRow();
        $csv = $header."\n".substr($row, strpos($row, "\n") + 1);
        $csv = rtrim($csv).',Internal note,MOH Procurement';

        $batch = $this->uploadCsv($user, $csv);
        $importRow = ImportRow::query()->where('import_batch_id', $batch->id)->first();

        $this->assertNotNull($importRow);
        $this->assertArrayHasKey('Notes', $importRow->raw_data['additional_columns'] ?? $importRow->raw_data['by_header'] ?? []);
    }

    public function test_fuzzy_header_mapping_maps_qty_column(): void
    {
        $user = User::factory()->create();

        $header = 'Code,INN,Product Name,Country,Tender Ref,Awarded price,Price USD,Winner,Supplier,Version,Year,QTY,Tender Value';
        $row = $this->csvRow();
        $csv = $header."\n".substr($row, strpos($row, "\n") + 1);

        $batch = $this->uploadCsv($user, $csv);
        $importRow = ImportRow::query()->where('import_batch_id', $batch->id)->first();

        $this->assertNotNull($importRow);
        $this->assertSame('1000', $importRow->raw_qty);
    }

    private function uploadAndConfirm(User $user, string $path): ImportBatch
    {
        $file = new UploadedFile($path, 'sample.csv', 'text/csv', null, true);

        $this->actingAs($user)->post(route('uploads.store'), ['file' => $file]);

        $batch = ImportBatch::query()->latest('id')->firstOrFail();
        $this->confirmMapping($user, $batch, $batch->metadata['mapped_headers'] ?? []);

        return $batch->fresh();
    }

    private function confirmMapping(User $user, ImportBatch $batch, array $mappedHeaders): void
    {
        $mapping = array_filter($mappedHeaders, fn ($index) => $index !== null);

        $this->actingAs($user)->post(route('imports.mapping.confirm', $batch), [
            'mapping' => $mapping,
        ])->assertRedirect(route('imports.show', $batch));
    }

    private function uploadCsv(User $user, string $csv): ImportBatch
    {
        $file = UploadedFile::fake()->createWithContent('import_test.csv', $csv);

        $response = $this->actingAs($user)->post(route('uploads.store'), [
            'file' => $file,
        ]);

        $batch = ImportBatch::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('imports.mapping', $batch));

        $this->confirmMapping($user, $batch, $batch->metadata['mapped_headers'] ?? []);

        return $batch->fresh();
    }

    private function csvRow(
        ?string $code = 'D001',
        ?string $inn = 'Paracetamol',
        ?string $productName = 'Paracetamol 500mg',
        ?string $country = 'Saudi Arabia',
        ?string $tenderNumber = 'T-2024-001',
        ?string $awardedPrice = '420',
        ?string $priceUsd = '425',
        ?string $winner = 'PharmaCorp',
        ?string $companyName = 'PharmaCorp International',
        ?string $version = 'v1',
        ?string $year = '2024',
        ?string $qty = '1000',
        ?string $tenderValue = '425000',
    ): string {
        $header = 'Code,INN,Product Name,Country,Tender #,Awarded price,Price USD,Winner,Company Name,Version,Year,Qty,Tender Value';
        $escape = static fn (?string $value): string => '"'.str_replace('"', '""', (string) $value).'"';

        $row = implode(',', [
            $escape($code),
            $escape($inn),
            $escape($productName),
            $escape($country),
            $escape($tenderNumber),
            $escape($awardedPrice),
            $escape($priceUsd),
            $escape($winner),
            $escape($companyName),
            $escape($version),
            $escape($year),
            $escape($qty),
            $escape($tenderValue),
        ]);

        return $header."\n".$row."\n";
    }
}
