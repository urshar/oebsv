<?php

namespace App\Services\Lenex;

use App\Models\Swimstyle;
use SimpleXMLElement;

class SwimstyleResolver
{
    /**
     * Findet oder erstellt einen Swimstyle aus einem LENEX-<SWIMSTYLE>-Knoten.
     *
     * Erwartete LENEX-Attribute:
     * - @distance   (Meter pro Schwimmer: 50, 100, 150, 200, ...)
     * - @stroke     (FREE, BACK, BREAST, FLY, MEDLEY)
     * - @relaycount (optional, default 1; >1 = Staffel)
     */
    public function resolveFromLenex(?SimpleXMLElement $swimstyleNode): ?Swimstyle
    {
        if (!$swimstyleNode instanceof SimpleXMLElement) {
            return null;
        }

        $distance   = (int) ($swimstyleNode['distance'] ?? 0);
        $strokeCode = strtoupper((string) ($swimstyleNode['stroke'] ?? ''));
        $relayCount = (int) ($swimstyleNode['relaycount'] ?? 1);

        if ($distance <= 0 || $strokeCode === '') {
            // Ungültiger Knoten
            return null;
        }

        $isRelay = $relayCount > 1;

        return Swimstyle::updateOrCreate(
            [
                'distance'    => $distance,
                'relaycount'  => $relayCount,
                'stroke_code' => $strokeCode,
            ],
            [
                'distance'       => $distance,
                'relaycount'     => $relayCount,
                'is_relay'       => $isRelay,
                'stroke_code'    => $strokeCode,
                // 'stroke' kannst du entweder als Code oder als Klartext führen
                'stroke'         => $strokeCode, // oder mapStrokeNameEn(), wenn du lieber "Freestyle" etc. willst
                'stroke_name_en' => $this->mapStrokeNameEn($strokeCode),
                'stroke_name_de' => $this->mapStrokeNameDe($strokeCode),
            ]
        );
    }

    protected function mapStrokeNameEn(string $code): string
    {
        return match ($code) {
            'FREE'   => 'Freestyle',
            'BACK'   => 'Backstroke',
            'BREAST' => 'Breaststroke',
            'FLY'    => 'Butterfly',
            'MEDLEY' => 'Individual Medley',
            default  => $code,
        };
    }

    protected function mapStrokeNameDe(string $code): string
    {
        return match ($code) {
            'FREE'   => 'Freistil',
            'BACK'   => 'R\u00fccken',
            'BREAST' => 'Brust',
            'FLY'    => 'Schmetterling',
            'MEDLEY' => 'Lagen',
            default  => $code,
        };
    }
}
