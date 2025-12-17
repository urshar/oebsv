<?php

namespace App\Services\Lenex\Preview;

use App\Models\ParaEvent;

class SwimstyleLabel
{
    public static function relay(?ParaEvent $event): string
    {
        if (!$event) {
            return '—';
        }

        $sw = $event->swimstyle;
        if (!$sw) {
            return "Event {$event->number}";
        }

        $relaycount = (int) ($sw->relaycount ?? 0);
        $distance = (int) ($sw->distance ?? 0);

        // Deutsch bevorzugen
        $strokeDe = trim((string) ($sw->stroke_name_de ?? ''));
        $stroke = trim((string) ($sw->stroke ?? ''));
        $strokeText = $strokeDe !== '' ? $strokeDe : $stroke;

        // Falls strokeText bereits "4x50m ..." enthält (kommt vor bei falschen Seeds),
        // nicht nochmal prefixen
        if (preg_match('/^\d+\s*x\s*\d+\s*m/i', $strokeText)) {
            return preg_replace('/\s+/', ' ', $strokeText);
        }

        if ($relaycount > 1 && $distance > 0) {
            return trim("{$relaycount}x{$distance}m {$strokeText}");
        }

        // Fallback
        return self::event($event);
    }


    public static function event(?ParaEvent $event): string
    {
        if (!$event) {
            return '—';
        }

        $sw = $event->swimstyle;
        if (!$sw) {
            return "Event {$event->number}";
        }

        $distance = (int) ($sw->distance ?? 0);
        $strokeDe = trim((string) ($sw->stroke_name_de ?? $sw->stroke ?? ''));

        if ($distance > 0) {
            return trim("{$distance}m {$strokeDe}");
        }

        return $strokeDe !== '' ? $strokeDe : "Event {$event->number}";
    }
}
