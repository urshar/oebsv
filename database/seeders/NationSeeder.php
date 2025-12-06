<?php

namespace Database\Seeders;

use App\Models\Continent;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class NationSeeder extends Seeder
{
    /**
     * @throws FileNotFoundException
     */
    public function run(): void
    {
        // Tabelle leeren
        DB::table('nations')->truncate();

        // SQL-Datei laden
        $sql = File::get(database_path('seeders/sql/nations.sql'));

        // Platzhalter-IOCs, die mehrfach vorkommen, auf NULL setzen
        $sql = str_replace(["'---'", "'--'"], 'NULL', $sql);

        // Roh-SQL ausführen (INSERT INTO "nations" ...)
        DB::unprepared($sql);

        // ---------------------------------------------------
        // 2. Kontinente korrekt setzen
        // ---------------------------------------------------

        // Continent-IDs nach Code holen: ['AF' => 1, 'AS' => 2, ...]
        $continentIds = Continent::pluck('id', 'code');

        // Erst mal alles zurücksetzen (damit die alten 1–7 aus der SQL weg sind)
        DB::table('nations')->update(['continent_id' => null]);

        // Afrika
        DB::table('nations')
            ->whereIn('subRegionName', ['Northern Africa', 'Sub-Saharan Africa'])
            ->update(['continent_id' => $continentIds['AF']]);

        // Asien
        DB::table('nations')
            ->whereIn('subRegionName', [
                'Central Asia',
                'Eastern Asia',
                'South-eastern Asia',
                'Southern Asia',
                'Western Asia',
            ])
            ->update(['continent_id' => $continentIds['AS']]);

        // Europa
        DB::table('nations')
            ->whereIn('subRegionName', [
                'Eastern Europe',
                'Northern Europe',
                'Southern Europe',
                'Western Europe',
            ])
            ->update(['continent_id' => $continentIds['EU']]);

        // Nordamerika (Nordamerika + Karibik + Zentralamerika)
        DB::table('nations')
            ->where('subRegionName', 'Northern America')
            ->update(['continent_id' => $continentIds['NA']]);

        DB::table('nations')
            ->where('subRegionName', 'Latin America and the Caribbean')
            ->whereIn('IntermediateRegionName', ['Caribbean', 'Central America'])
            ->update(['continent_id' => $continentIds['NA']]);

        // Südamerika
        DB::table('nations')
            ->where('subRegionName', 'Latin America and the Caribbean')
            ->where('IntermediateRegionName', 'South America')
            ->update(['continent_id' => $continentIds['SA']]);

        // Ozeanien
        DB::table('nations')
            ->whereIn('subRegionName', [
                'Australia and New Zealand',
                'Melanesia',
                'Micronesia',
                'Polynesia',
            ])
            ->update(['continent_id' => $continentIds['OC']]);

        // Antarktis
        DB::table('nations')
            ->where('nameEn', 'Antarctica')
            ->update(['continent_id' => $continentIds['AN']]);

        // Sonderfall Taipei (hat in den Daten kein subRegionName)
        DB::table('nations')
            ->where('nameEn', 'Taipei')
            ->update(['continent_id' => $continentIds['AS']]);
    }
}
