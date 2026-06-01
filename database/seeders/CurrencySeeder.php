<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'is_default' => true],
            ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => 'SR', 'is_default' => false],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'AED', 'is_default' => false],
            ['code' => 'EGP', 'name' => 'Egyptian Pound', 'symbol' => 'E£', 'is_default' => false],
            ['code' => 'IQD', 'name' => 'Iraqi Dinar', 'symbol' => 'IQD', 'is_default' => false],
            ['code' => 'JOD', 'name' => 'Jordanian Dinar', 'symbol' => 'JD', 'is_default' => false],
            ['code' => 'KWD', 'name' => 'Kuwaiti Dinar', 'symbol' => 'KD', 'is_default' => false],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                [
                    'name' => $currency['name'],
                    'symbol' => $currency['symbol'],
                    'decimal_places' => 2,
                    'is_default' => $currency['is_default'],
                    'is_active' => true,
                ]
            );
        }
    }
}
