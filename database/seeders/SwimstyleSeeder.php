<?php

namespace Database\Seeders;

use App\Models\Swimstyle;
use Illuminate\Database\Seeder;

class SwimstyleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $styles = [];

        // Helper für Einzelstrecken
        $addStroke = function (
            string $strokeCode,
            string $nameEn,
            string $nameDe,
            array $distances
        ) use (&$styles) {
            foreach ($distances as $distance) {
                $styles[] = [
                    'distance'        => $distance,
                    'relaycount'      => 1,
                    'is_relay'        => false,
                    'stroke_code'     => $strokeCode,
                    'stroke'          => $nameEn,
                    'stroke_name_en'  => $nameEn,
                    'stroke_name_de'  => $nameDe,
                ];
            }
        };

        // Einzelstrecken – orientiert am aktuellen Para-Programm (LA28 / WPS)
        $addStroke('FREE',   'Freestyle',          'Freistil',       [25, 50, 100, 200, 400, 800, 1500]);
        $addStroke('BACK',   'Backstroke',         'Rücken',         [25, 50, 100, 200]);
        $addStroke('BREAST', 'Breaststroke',       'Brust',          [25, 50, 100, 200]);
        $addStroke('FLY',    'Butterfly',          'Schmetterling',  [25, 50, 100, 200]);
        // Para-spezifische Lagen-Strecken
        $addStroke('MEDLEY', 'Individual Medley',  'Lagen',          [150, 200, 400]);

        // Staffeln – LENEX-Logik:
        // distance = Bahnlänge, relaycount = Anzahl Schwimmer
        $styles[] = [
            'distance'        => 25,
            'relaycount'      => 4,
            'is_relay'        => true,
            'stroke_code'     => 'FREE',
            'stroke'          => 'Freestyle relay',
            'stroke_name_en'  => '4x25m Freestyle',
            'stroke_name_de'  => '4x25m Freistil',
        ];

        $styles[] = [
            'distance'        => 50,
            'relaycount'      => 4,
            'is_relay'        => true,
            'stroke_code'     => 'FREE',
            'stroke'          => 'Freestyle relay',
            'stroke_name_en'  => '4x50m Freestyle',
            'stroke_name_de'  => '4x50m Freistil',
        ];

        $styles[] = [
            'distance'        => 100,
            'relaycount'      => 4,
            'is_relay'        => true,
            'stroke_code'     => 'FREE',
            'stroke'          => 'Freestyle relay',
            'stroke_name_en'  => '4x100m Freestyle',
            'stroke_name_de'  => '4x100m Freistil',
        ];

        $styles[] = [
            'distance'        => 200,
            'relaycount'      => 4,
            'is_relay'        => true,
            'stroke_code'     => 'FREE',
            'stroke'          => 'Freestyle relay',
            'stroke_name_en'  => '4x200m Freestyle',
            'stroke_name_de'  => '4x200m Freistil',
        ];

        $styles[] = [
            'distance'        => 25,
            'relaycount'      => 4,
            'is_relay'        => true,
            'stroke_code'     => 'MEDLEY',
            'stroke'          => 'Medley relay',
            'stroke_name_en'  => '4x25m Medley',
            'stroke_name_de'  => '4x25m Lagen',
        ];

        $styles[] = [
            'distance'        => 50,
            'relaycount'      => 4,
            'is_relay'        => true,
            'stroke_code'     => 'MEDLEY',
            'stroke'          => 'Medley relay',
            'stroke_name_en'  => '4x50m Medley',
            'stroke_name_de'  => '4x50m Lagen',
        ];

        $styles[] = [
            'distance'        => 100,
            'relaycount'      => 4,
            'is_relay'        => true,
            'stroke_code'     => 'MEDLEY',
            'stroke'          => 'Medley relay',
            'stroke_name_en'  => '4x100m Medley',
            'stroke_name_de'  => '4x100m Lagen',
        ];

        // Upsert per (distance, relaycount, stroke_code),
        // damit Mehrfach-Seed kein Problem ist
        foreach ($styles as $data) {
            Swimstyle::updateOrCreate(
                [
                    'distance'   => $data['distance'],
                    'relaycount' => $data['relaycount'],
                    'stroke_code'=> $data['stroke_code'],
                ],
                $data
            );
        }
    }
}
