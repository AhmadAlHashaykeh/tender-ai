<?php

namespace App\Services\Dev;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportRowValidationStatus;
use App\Models\AiUsageLog;
use App\Models\AuditLog;
use App\Models\BidRecord;
use App\Models\CachedMarketStatistic;
use App\Models\Company;
use App\Models\CompanyAlias;
use App\Models\Country;
use App\Models\Drug;
use App\Models\DrugAlias;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\ImportRowDuplicate;
use App\Models\OutlierFlag;
use App\Models\Prediction;
use App\Models\PredictionAccuracyRecord;
use App\Models\PredictionCalculation;
use App\Models\PredictionContextSnapshot;
use App\Models\PredictionHistoricalRef;
use App\Models\PredictionScenario;
use App\Models\PricingStatistic;
use App\Models\StandardizationLog;
use App\Models\StandardizationSuggestion;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Models\TenderItem;
use App\Models\User;
use App\Services\Import\DuplicateDetectionService;
use App\Services\Import\ImportValidatorService;
use App\Support\Normalization\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenderAiTestDataService
{
    /** @var list<array<string, string|int|float>> */
    public const TEST_ROWS = [
        [
            'code' => '51343110003',
            'inn' => 'ABACAVIR 600MG/LAMIVUDINE 300MG TABLET',
            'product_name' => 'Abacavir 600Mg/Lamivudine 300Mg Tab',
            'country' => 'KSA',
            'tender_number' => 'NPT 01/17',
            'awarded_price' => '13.55',
            'price_usd' => '13.5493',
            'winner' => 'GLAXO SAUDI ARABIA LIMITED',
            'company_name' => 'Glaxo Saudi Arabia Limited',
            'version' => 'V3',
            'year' => '2017',
            'qty' => '5000',
            'tender_value' => '67746.50',
        ],
        [
            'code' => '51201599001',
            'inn' => 'ABATACEPT 250MG INJECTION VIAL OR PREFILLED SYRINGE',
            'product_name' => 'Abatacept 250Mg Injection Vial Or P',
            'country' => 'KSA',
            'tender_number' => 'NPT 01/17',
            'awarded_price' => '302.19',
            'price_usd' => '302.1867',
            'winner' => 'Bristol Myers Squibb',
            'company_name' => 'Bristol Myers Squibb',
            'version' => 'V3',
            'year' => '2017',
            'qty' => '8900',
            'tender_value' => '2689461.63',
        ],
        [
            'code' => '51131701000',
            'inn' => 'ABCIXIMAB 2MG/ML INJECTION',
            'product_name' => 'Abciximab 2Mg/Ml Injection',
            'country' => 'KSA',
            'tender_number' => 'NPT 01/17',
            'awarded_price' => '214.97',
            'price_usd' => '214.9733',
            'winner' => 'Yonsei',
            'company_name' => 'Yonsei',
            'version' => 'V3',
            'year' => '2017',
            'qty' => '1657',
            'tender_value' => '356210.76',
        ],
        [
            'code' => '5111183000000',
            'inn' => 'ABIRATERONE ACETATE 250 MG TABLET',
            'product_name' => 'Abiraterone Acetate 250 Mg Tablet',
            'country' => 'KSA',
            'tender_number' => 'NPT 15/20',
            'awarded_price' => '5.25',
            'price_usd' => '5.2480',
            'winner' => 'Arab Pharmaceutical Manufacturing Co.Lt',
            'company_name' => 'Arab Pharmaceutical Manufacturing Co.Lt',
            'version' => 'V1',
            'year' => '2022',
            'qty' => '249260',
            'tender_value' => '1308116.48',
        ],
        [
            'code' => '5111183000000',
            'inn' => 'ABIRATERONE ACETATE 250 MG TABLET',
            'product_name' => 'Abiraterone Acetate 250 Mg Tablet',
            'country' => 'KSA',
            'tender_number' => 'NPT 15/20',
            'awarded_price' => '6.86',
            'price_usd' => '6.8571',
            'winner' => 'SUDAIR PHARMA',
            'company_name' => 'Sudair Pharma',
            'version' => 'V1',
            'year' => '2022',
            'qty' => '249260',
            'tender_value' => '1709200.75',
        ],
        [
            'code' => '5111183000000',
            'inn' => 'ABIRATERONE ACETATE 250 MG TABLET',
            'product_name' => 'Abiraterone Acetate 250 Mg Tablet',
            'country' => 'KSA',
            'tender_number' => 'NPT 01/17',
            'awarded_price' => '28.66',
            'price_usd' => '28.6643',
            'winner' => 'Pathion France',
            'company_name' => 'Pathion France',
            'version' => 'V3',
            'year' => '2017',
            'qty' => '62320',
            'tender_value' => '1786359.18',
        ],
    ];

    /** @var list<string> */
    protected const AUDITABLE_TYPES = [
        'App\Models\ImportBatch',
        'App\Models\ImportRow',
        'App\Models\Prediction',
        'App\Models\BidRecord',
        'App\Models\StandardizationSuggestion',
    ];

    public function __construct(
        protected ImportValidatorService $validator,
        protected DuplicateDetectionService $duplicateDetector,
        protected TextNormalizer $normalizer,
    ) {}

    public function clearDomainData(): void
    {
        DB::transaction(function (): void {
            PredictionAccuracyRecord::query()->delete();
            PredictionHistoricalRef::query()->delete();
            PredictionContextSnapshot::query()->delete();
            PredictionScenario::query()->delete();
            PredictionCalculation::query()->delete();
            Prediction::query()->delete();
            AiUsageLog::query()->delete();

            AuditLog::query()
                ->where(function ($query): void {
                    foreach (self::AUDITABLE_TYPES as $type) {
                        $query->orWhere('auditable_type', $type);
                    }
                    $query->orWhere('action', 'like', '%import%')
                        ->orWhere('action', 'like', '%prediction%')
                        ->orWhere('action', 'like', '%standardiz%');
                })
                ->delete();

            OutlierFlag::query()->delete();
            CachedMarketStatistic::query()->delete();
            PricingStatistic::query()->delete();

            ImportRow::query()->update([
                'bid_record_id' => null,
                'tender_id' => null,
                'tender_item_id' => null,
                'standardized_drug_id' => null,
                'company_id' => null,
                'standardization_suggestion_id' => null,
            ]);

            BidRecord::query()->delete();
            TenderItem::query()->delete();
            Tender::query()->delete();
            StandardizationLog::query()->delete();
            StandardizationSuggestion::query()->delete();
            ImportRowDuplicate::query()->delete();
            ImportRow::query()->delete();
            ImportBatch::query()->delete();
            Drug::query()->delete();
            DrugAlias::query()->delete();
            CompanyAlias::query()->delete();
            Company::query()->delete();
            StandardizedDrug::query()->delete();
        });
    }

    public function seedReferenceEntities(): void
    {
        $saudi = Country::query()->where('code', 'SA')->firstOrFail();

        $drugDefinitions = [
            '51343110003' => [
                'inn' => 'ABACAVIR 600MG/LAMIVUDINE 300MG TABLET',
                'product_name' => 'Abacavir 600Mg/Lamivudine 300Mg Tab',
            ],
            '51201599001' => [
                'inn' => 'ABATACEPT 250MG INJECTION VIAL OR PREFILLED SYRINGE',
                'product_name' => 'Abatacept 250Mg Injection Vial Or P',
            ],
            '51131701000' => [
                'inn' => 'ABCIXIMAB 2MG/ML INJECTION',
                'product_name' => 'Abciximab 2Mg/Ml Injection',
            ],
            '5111183000000' => [
                'inn' => 'ABIRATERONE ACETATE 250 MG TABLET',
                'product_name' => 'Abiraterone Acetate 250 Mg Tablet',
            ],
        ];

        foreach ($drugDefinitions as $code => $definition) {
            $innNormalized = $this->normalizer->normalizeDrugInn($definition['inn']);
            $productNormalized = $this->normalizer->normalizeDrugProductName($definition['product_name']);
            $components = $this->normalizer->extractDrugComponents($definition['product_name']);

            $drug = StandardizedDrug::query()->updateOrCreate(
                ['code' => $code],
                [
                    'inn' => $innNormalized,
                    'display_name' => $definition['product_name'],
                    'product_name_normalized' => $productNormalized,
                    'strength' => $components['strength'],
                    'strength_unit' => $components['strength_unit'],
                    'form' => $components['form'],
                    'is_active' => true,
                    'source' => 'test_seed',
                ]
            );

            foreach (array_filter([$productNormalized, $innNormalized]) as $alias) {
                DrugAlias::query()->updateOrCreate(
                    [
                        'standardized_drug_id' => $drug->id,
                        'normalized_alias' => $alias,
                    ],
                    [
                        'alias_value' => $alias,
                        'alias_type' => 'product_name',
                        'source' => 'test_seed',
                        'confidence' => 95,
                    ]
                );
            }
        }

        $companyNames = [
            'Glaxo Saudi Arabia Limited',
            'Bristol Myers Squibb',
            'Yonsei',
            'Arab Pharmaceutical Manufacturing Co.Lt',
            'Sudair Pharma',
            'Pathion France',
        ];

        $extraAliases = [
            'GLAXO SAUDI ARABIA LIMITED' => 'Glaxo Saudi Arabia Limited',
            'SUDAIR PHARMA' => 'Sudair Pharma',
        ];

        foreach ($companyNames as $name) {
            $normalized = $this->normalizer->normalizeCompanyName($name);

            $company = Company::query()->updateOrCreate(
                ['normalized_name' => $normalized],
                [
                    'name' => $name,
                    'country_id' => $saudi->id,
                    'is_active' => true,
                    'source' => 'test_seed',
                ]
            );

            CompanyAlias::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'normalized_alias' => $normalized,
                ],
                [
                    'alias_value' => $name,
                    'alias_type' => 'legal_name',
                    'source' => 'test_seed',
                    'confidence' => 95,
                ]
            );
        }

        foreach ($extraAliases as $aliasValue => $canonicalName) {
            $company = Company::query()->where('name', $canonicalName)->first();

            if ($company === null) {
                continue;
            }

            $normalizedAlias = $this->normalizer->normalizeCompanyName($aliasValue);

            if ($normalizedAlias === null) {
                continue;
            }

            CompanyAlias::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'normalized_alias' => $normalizedAlias,
                ],
                [
                    'alias_value' => $aliasValue,
                    'alias_type' => 'trade_name',
                    'source' => 'test_seed',
                    'confidence' => 95,
                ]
            );
        }
    }

    public function seedImportBatchAndRows(?User $uploader = null): ImportBatch
    {
        $uploader ??= User::query()->orderBy('id')->first();

        $batch = ImportBatch::query()->create([
            'uuid' => (string) Str::uuid(),
            'filename' => 'tenderai-test-data.csv',
            'original_filename' => 'tenderai-controlled-test-data.csv',
            'file_path' => null,
            'file_hash' => hash('sha256', 'tenderai-controlled-test-data'),
            'uploaded_by' => $uploader?->id,
            'row_count' => 0,
            'processed_count' => 0,
            'success_count' => 0,
            'error_count' => 0,
            'duplicate_count' => 0,
            'status' => ImportBatchStatus::Validating->value,
            'source_type' => 'test_seed',
            'metadata' => [
                'seed' => 'tenderai:seed-test-data',
                'mapped_headers' => [],
                'detected_headers' => [],
            ],
            'started_at' => now(),
        ]);

        $rowNumber = 1;

        foreach (self::TEST_ROWS as $canonical) {
            $this->createImportRow($batch, $canonical, $rowNumber);
            $rowNumber++;
        }

        $duplicateStats = $this->duplicateDetector->detectForBatch($batch->id);

        $rows = ImportRow::query()->where('import_batch_id', $batch->id);
        $total = (clone $rows)->count();
        $invalid = (clone $rows)->where('validation_status', ImportRowValidationStatus::Invalid->value)->count();
        $valid = (clone $rows)->where('validation_status', ImportRowValidationStatus::Valid->value)->count();

        $batch->update([
            'row_count' => $total,
            'processed_count' => $total,
            'success_count' => $valid,
            'error_count' => $invalid,
            'duplicate_count' => $duplicateStats['duplicate_count'],
            'status' => $invalid > 0
                ? ImportBatchStatus::CompletedWithErrors->value
                : ImportBatchStatus::Completed->value,
            'metadata' => array_merge($batch->metadata ?? [], [
                'total_rows' => $total,
                'valid_rows' => $valid,
                'invalid_rows' => $invalid,
                'warning_rows' => 0,
                'duplicate_rows' => 0,
                'validation_review_rows' => 0,
            ]),
            'completed_at' => now(),
        ]);

        return $batch->fresh();
    }

    /**
     * @param  array<string, string|int|float>  $canonical
     */
    protected function createImportRow(ImportBatch $batch, array $canonical, int $rowNumber): ImportRow
    {
        $stringCanonical = array_map(
            fn ($value) => is_scalar($value) ? (string) $value : '',
            $canonical
        );

        $validation = $this->validator->validate($stringCanonical);
        $normalized = $validation['normalized_data'];
        $rowHash = $this->duplicateDetector->generateRowHash($normalized);

        return ImportRow::query()->create([
            'import_batch_id' => $batch->id,
            'row_number' => $rowNumber,
            'row_hash' => $rowHash,
            'raw_code' => $stringCanonical['code'],
            'raw_inn' => $stringCanonical['inn'],
            'raw_product_name' => $stringCanonical['product_name'],
            'raw_country' => $stringCanonical['country'],
            'raw_tender_number' => $stringCanonical['tender_number'],
            'raw_awarded_price' => $stringCanonical['awarded_price'],
            'raw_price_usd' => $stringCanonical['price_usd'],
            'raw_winner' => $stringCanonical['winner'],
            'raw_company_name' => $stringCanonical['company_name'],
            'raw_version' => $stringCanonical['version'],
            'raw_year' => $stringCanonical['year'],
            'raw_qty' => $stringCanonical['qty'],
            'raw_tender_value' => $stringCanonical['tender_value'],
            'raw_data' => [
                'by_header' => [],
                'canonical' => $stringCanonical,
            ],
            'normalized_data' => $normalized,
            'validation_status' => $validation['validation_status'],
            'standardization_status' => 'pending',
            'row_type' => 'winning_bid',
            'confidence_score' => $validation['confidence_score'],
            'error_message' => $validation['error_message'],
            'warning_messages' => $validation['warning_messages'],
        ]);
    }

    /**
     * @return array<string, int|list<string>>
     */
    public function countsSummary(): array
    {
        return [
            'import_batches' => ImportBatch::query()->count(),
            'import_rows' => ImportRow::query()->count(),
            'standardized_drugs' => StandardizedDrug::query()->count(),
            'companies' => Company::query()->count(),
            'tenders' => Tender::query()->count(),
            'tender_items' => TenderItem::query()->count(),
            'bid_records' => BidRecord::query()->count(),
            'pricing_statistics' => PricingStatistic::query()->count(),
            'predictions' => Prediction::query()->count(),
            'standardized_drug_names' => StandardizedDrug::query()
                ->orderBy('code')
                ->get(['code', 'inn', 'display_name'])
                ->map(fn (StandardizedDrug $drug) => sprintf(
                    '%s | %s | %s',
                    $drug->code ?? '—',
                    strtoupper((string) $drug->inn),
                    $drug->display_name
                ))
                ->all(),
            'company_names' => Company::query()->orderBy('name')->pluck('name')->all(),
        ];
    }
}
