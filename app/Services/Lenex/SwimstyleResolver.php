<?php

namespace App\Services\Lenex;

use App\Models\Swimstyle;
use SimpleXMLElement;

class SwimstyleResolver
{
    /**
     * Findet oder erstellt einen Swimstyle aus einem LENEX-<SWIMSTYLE>-Knoten.
     * WICHTIG: Bestehende Swimstyles werden NICHT überschrieben (kein updateOrCreate),
     * damit Seeder-Werte (stroke_name_en/de) stabil bleiben.
     */
    public function resolveFromLenex(?SimpleXMLElement $swimstyleNode): ?Swimstyle
    {
        if (!$swimstyleNode instanceof SimpleXMLElement) {
            return null;
        }

        $distance = (int) ($swimstyleNode['distance'] ?? 0);
        $strokeRaw = strtoupper(trim((string) ($swimstyleNode['stroke'] ?? '')));
        $relayCount = (int) ($swimstyleNode['relaycount'] ?? 1);

        if ($relayCount <= 0) {
            $relayCount = 1;
        }

        $strokeCode = $this->normalizeStrokeCode($strokeRaw);

        if ($distance <= 0 || $strokeCode === '') {
            return null;
        }

        $key = [
            'distance' => $distance,
            'relaycount' => $relayCount,
            'stroke_code' => $strokeCode,
        ];

        // ✅ Wenn vorhanden: nur zurückgeben, NICHT updaten
        $existing = Swimstyle::where($key)->first();
        if ($existing) {
            return $existing;
        }

        // ✅ Nur bei nicht vorhanden: anlegen
        $isRelay = $relayCount > 1;

        return Swimstyle::create($key + [
                'is_relay' => $isRelay,
                // konsistent zum Seeder: "stroke" = EN-Name
                'stroke' => $this->mapStrokeNameEn($strokeCode),
                'stroke_name_en' => $this->mapStrokeNameEn($strokeCode),
                'stroke_name_de' => $this->mapStrokeNameDe($strokeCode),
            ]);
    }

    private function normalizeStrokeCode(string $code): string
    {
        $c = strtoupper(trim($code));

        return match ($c) {
            'FREE', 'FREESTYLE' => 'FREE',
            'BACK', 'BACKSTROKE' => 'BACK',
            'BREAST', 'BREASTSTROKE' => 'BREAST',
            'FLY', 'BUTTERFLY' => 'FLY',
            'MEDLEY', 'IM', 'INDIVIDUALMEDLEY' => 'MEDLEY',
            default => $c,
        };
    }

    protected function mapStrokeNameEn(string $code): string
    {
        return match ($code) {
            'FREE' => 'Freestyle',
            'BACK' => 'Backstroke',
            'BREAST' => 'Breaststroke',
            'FLY' => 'Butterfly',
            'MEDLEY' => 'Individual Medley',
            default => $code,
        };
    }

    protected function mapStrokeNameDe(string $code): string
    {
        return match ($code) {
            'FREE' => 'Freistil',
            'BACK' => 'Rücken',
            'BREAST' => 'Brust',
            'FLY' => 'Schmetterling',
            'MEDLEY' => 'Lagen',
            default => $code,
        };
    }
}
