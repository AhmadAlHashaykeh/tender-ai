<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            ['name' => 'GCC', 'code' => 'GCC', 'description' => 'Gulf Cooperation Council'],
            ['name' => 'North Africa', 'code' => 'NORTH_AFRICA', 'description' => 'North African markets'],
            ['name' => 'Levant', 'code' => 'LEVANT', 'description' => 'Levant region'],
        ];

        foreach ($regions as $region) {
            Region::updateOrCreate(
                ['code' => $region['code']],
                array_merge($region, ['is_active' => true])
            );
        }
    }
}
