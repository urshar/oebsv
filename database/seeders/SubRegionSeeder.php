<?php

namespace Database\Seeders;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SubRegionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * @throws FileNotFoundException
     */
    public function run(): void
    {
        DB::table('subregions')->truncate();
        $sql = File::get(database_path('seeders/sql/subregion.sql'));
        DB::unprepared($sql);
    }
}
