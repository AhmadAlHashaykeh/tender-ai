<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyAlias;
use App\Models\Country;
use App\Models\DrugAlias;
use App\Models\StandardizedDrug;
use App\Support\Normalization\TextNormalizer;
use Illuminate\Database\Seeder;

class StandardizationReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $normalizer = new TextNormalizer;

        $saudi = Country::where('code', 'SA')->first();
        $uae = Country::where('code', 'AE')->first();

        $paracetamol = StandardizedDrug::updateOrCreate(
            ['code' => 'D001'],
            [
                'inn' => 'paracetamol',
                'display_name' => 'Paracetamol 500mg Tablets',
                'product_name_normalized' => 'paracetamol 500mg tablets',
                'strength' => '500',
                'strength_unit' => 'mg',
                'form' => 'tablet',
                'is_active' => true,
                'source' => 'seed',
            ]
        );

        DrugAlias::updateOrCreate(
            [
                'standardized_drug_id' => $paracetamol->id,
                'normalized_alias' => 'paracetamol 500mg',
            ],
            [
                'alias_value' => 'Paracetamol 500mg',
                'alias_type' => 'product_name',
                'source' => 'seed',
                'confidence' => 95,
            ]
        );

        StandardizedDrug::updateOrCreate(
            ['code' => 'D002'],
            [
                'inn' => 'amoxicillin',
                'display_name' => 'Amoxicillin 250mg',
                'product_name_normalized' => 'amoxicillin 250mg',
                'is_active' => true,
                'source' => 'seed',
            ]
        );

        $pharmaCorp = Company::updateOrCreate(
            ['normalized_name' => $normalizer->normalizeCompanyName('PharmaCorp International')],
            [
                'name' => 'PharmaCorp International',
                'country_id' => $saudi?->id,
                'is_active' => true,
                'source' => 'seed',
            ]
        );

        CompanyAlias::updateOrCreate(
            [
                'company_id' => $pharmaCorp->id,
                'normalized_alias' => $normalizer->normalizeCompanyName('PharmaCorp'),
            ],
            [
                'alias_value' => 'PharmaCorp',
                'alias_type' => 'trade_name',
                'source' => 'seed',
                'confidence' => 95,
            ]
        );

        Company::updateOrCreate(
            ['normalized_name' => $normalizer->normalizeCompanyName('HealthMed Inc')],
            [
                'name' => 'HealthMed Inc',
                'country_id' => $uae?->id,
                'is_active' => true,
                'source' => 'seed',
            ]
        );
    }
}
