<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Continent;

class ContinentSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['code' => 'AF', 'nameEn' => 'Africa',  'nameDe' => 'Afrika'],
            ['code' => 'AS', 'nameEn' => 'Asia',    'nameDe' => 'Asien'],
            ['code' => 'EU', 'nameEn' => 'Europe',  'nameDe' => 'Europa'],
            ['code' => 'NA', 'nameEn' => 'North America', 'nameDe' => 'Nordamerika'],
            ['code' => 'SA', 'nameEn' => 'South America', 'nameDe' => 'Südamerika'],
            ['code' => 'OC', 'nameEn' => 'Oceania', 'nameDe' => 'Ozeanien'],
            ['code' => 'AN', 'nameEn' => 'Antarctica', 'nameDe' => 'Antarktis'],
        ];

        foreach ($data as $row) {
            Continent::updateOrCreate(
                ['code' => $row['code']],
                $row
            );
        }
    }
}
