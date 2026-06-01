<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Currency;
use App\Models\Region;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $gcc = Region::where('code', 'GCC')->first();
        $northAfrica = Region::where('code', 'NORTH_AFRICA')->first();
        $levant = Region::where('code', 'LEVANT')->first();

        $currencyByCode = Currency::pluck('id', 'code');

        $countries = [
            ['name' => 'Saudi Arabia', 'code' => 'SA', 'iso_code_2' => 'SA', 'iso_code_3' => 'SAU', 'region' => $gcc, 'currency' => 'SAR'],
            ['name' => 'United Arab Emirates', 'code' => 'AE', 'iso_code_2' => 'AE', 'iso_code_3' => 'ARE', 'region' => $gcc, 'currency' => 'AED'],
            ['name' => 'Kuwait', 'code' => 'KW', 'iso_code_2' => 'KW', 'iso_code_3' => 'KWT', 'region' => $gcc, 'currency' => 'KWD'],
            ['name' => 'Egypt', 'code' => 'EG', 'iso_code_2' => 'EG', 'iso_code_3' => 'EGY', 'region' => $northAfrica, 'currency' => 'EGP'],
            ['name' => 'Iraq', 'code' => 'IQ', 'iso_code_2' => 'IQ', 'iso_code_3' => 'IRQ', 'region' => $levant, 'currency' => 'IQD'],
            ['name' => 'Jordan', 'code' => 'JO', 'iso_code_2' => 'JO', 'iso_code_3' => 'JOR', 'region' => $levant, 'currency' => 'JOD'],
        ];

        foreach ($countries as $country) {
            Country::updateOrCreate(
                ['code' => $country['code']],
                [
                    'name' => $country['name'],
                    'region_id' => $country['region']?->id,
                    'iso_code_2' => $country['iso_code_2'],
                    'iso_code_3' => $country['iso_code_3'],
                    'default_currency_id' => $currencyByCode[$country['currency']] ?? null,
                    'is_active' => true,
                ]
            );
        }
    }
}
