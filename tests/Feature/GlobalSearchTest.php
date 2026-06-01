<?php

namespace Tests\Feature;

use App\Models\BidRecord;
use App\Models\Company;
use App\Models\Country;
use App\Models\Currency;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Prediction;
use App\Models\StandardizedDrug;
use App\Models\Tender;
use App\Models\TenderItem;
use App\Models\User;
use Database\Seeders\CountrySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\RegionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Country $country;

    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RegionSeeder::class,
            CurrencySeeder::class,
            CountrySeeder::class,
        ]);

        $this->user = User::factory()->create();
        $this->country = Country::query()->where('name', 'Saudi Arabia')->firstOrFail();
        $this->currency = Currency::query()->where('code', 'USD')->firstOrFail();
    }

    public function test_global_search_requires_authentication(): void
    {
        $this->getJson(route('global-search', ['q' => 'test']))
            ->assertUnauthorized();
    }

    public function test_global_search_validates_minimum_query_length(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('global-search', ['q' => 'a']))
            ->assertUnprocessable();
    }

    public function test_global_search_returns_matching_tender(): void
    {
        $this->createBidRecord([
            'tender_number' => 'GHC-2025-SEARCH',
            'tender_title' => 'GHC 2025 Tender',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('global-search', ['q' => 'GHC-2025']));

        $response->assertOk()
            ->assertJsonStructure(['tenders', 'drugs', 'companies', 'predictions']);

        $titles = collect($response->json('tenders'))->pluck('title');
        $this->assertTrue($titles->contains('GHC 2025 Tender'));
    }

    public function test_global_search_predictions_are_scoped_to_current_user(): void
    {
        $record = $this->createBidRecord(['drug_display' => 'Scoped Drug XYZ']);

        $otherUser = User::factory()->create();

        $otherPrediction = Prediction::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $otherUser->id,
            'tender_id' => $record->tender_id,
            'standardized_drug_id' => $record->standardized_drug_id,
            'currency_id' => $this->currency->id,
            'status' => 'completed',
            'source' => 'test',
        ]);

        $mine = Prediction::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->user->id,
            'tender_id' => $record->tender_id,
            'standardized_drug_id' => $record->standardized_drug_id,
            'currency_id' => $this->currency->id,
            'status' => 'completed',
            'source' => 'test',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('global-search', ['q' => 'Scoped Drug']))
            ->assertOk();

        $predictionIds = collect($response->json('predictions'))->pluck('id');
        $this->assertTrue($predictionIds->contains($mine->uuid));
        $this->assertFalse($predictionIds->contains($otherPrediction->uuid));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function createBidRecord(array $options = []): BidRecord
    {
        $country = $options['country'] ?? $this->country;

        $drug = StandardizedDrug::query()->create([
            'code' => $options['drug_code'] ?? 'TN-'.uniqid(),
            'inn' => $options['drug_inn'] ?? 'Test Inn',
            'display_name' => $options['drug_display'] ?? 'Test Drug',
            'is_active' => true,
            'source' => 'test',
        ]);

        $companyName = $options['company_name'] ?? 'Test Company';
        $company = Company::query()->firstOrCreate(
            ['normalized_name' => strtolower($companyName)],
            [
                'name' => $companyName,
                'is_active' => true,
                'source' => 'test',
            ],
        );

        $tender = $options['tender'] ?? Tender::query()->create([
            'tender_number' => $options['tender_number'] ?? 'T-'.uniqid(),
            'country_id' => $country->id,
            'year' => $options['tender_year'] ?? 2024,
            'version' => $options['version'] ?? 'v1',
            'title' => $options['tender_title'] ?? null,
            'status' => 'active',
        ]);

        $item = TenderItem::query()->create([
            'tender_id' => $tender->id,
            'standardized_drug_id' => $drug->id,
            'description' => $options['product_description'] ?? 'Test product line',
        ]);

        $batch = ImportBatch::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'filename' => 'global-search-test.csv',
            'original_filename' => 'global-search-test.csv',
            'file_path' => 'imports/global-search-test.csv',
            'file_hash' => hash('sha256', uniqid('', true)),
            'row_count' => 1,
            'status' => 'completed',
            'source_type' => 'csv',
        ]);

        $importRow = ImportRow::query()->create([
            'import_batch_id' => $batch->id,
            'row_number' => 1,
            'row_hash' => hash('sha256', uniqid('', true)),
            'raw_code' => $options['raw_code'] ?? $drug->code,
            'raw_inn' => $options['raw_inn'] ?? $drug->inn,
            'raw_product_name' => $options['raw_product_name'] ?? 'Raw product',
            'raw_country' => $country->name,
            'raw_tender_number' => $tender->tender_number,
            'raw_company_name' => $company->name,
            'raw_year' => (string) ($options['tender_year'] ?? 2024),
            'validation_status' => 'valid',
            'standardization_status' => 'auto_approved',
            'row_type' => 'winning_bid',
            'raw_data' => ['note' => 'immutable'],
        ]);

        return BidRecord::query()->create([
            'tender_item_id' => $item->id,
            'tender_id' => $tender->id,
            'standardized_drug_id' => $drug->id,
            'company_id' => $company->id,
            'country_id' => $country->id,
            'currency_id' => $this->currency->id,
            'bid_status' => $options['bid_status'] ?? 'awarded',
            'is_winner' => $options['is_winner'] ?? true,
            'row_type' => 'winning_bid',
            'price_usd' => $options['price_usd'] ?? 100,
            'original_awarded_price' => $options['original_awarded_price'] ?? 95,
            'quantity' => $options['quantity'] ?? 1000,
            'tender_value' => $options['tender_value'] ?? 100000,
            'award_year' => $options['award_year'] ?? ($options['tender_year'] ?? 2024),
            'source_import_row_id' => $importRow->id,
            'import_batch_id' => $batch->id,
            'is_analytics_ready' => $options['is_analytics_ready'] ?? true,
            'excluded_from_stats' => $options['excluded_from_stats'] ?? false,
        ]);
    }
}
