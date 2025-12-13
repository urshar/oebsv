<?php

namespace App\Support;

final class SwimTime
{
    /**
     * Accepts:
     * - HH:MM:SS.xxx
     * - MM:SS.xxx
     * - SS.xxx
     * with '.' or ',' as decimal separator
     */
    public static function parseToMs(?string $time): ?int
    {
        $time = trim((string) $time);
        if ($time === '' || strtoupper($time) === 'NT') {
            return null;
        }

        $time = str_replace(',', '.', $time);

        // SS(.fff)
        if (preg_match('/^(\d{1,2})(?:\.(\d{1,3}))?$/', $time, $m)) {
            $sec = (int) $m[1];
            $ms = $sec * 1000;
            $ms += self::fracToMs($m[2] ?? '');
            return $ms;
        }

        // MM:SS(.fff)  OR  HH:MM:SS(.fff)
        if (!preg_match('/^(?:(\d{1,2}):)?(\d{1,2}):(\d{2})(?:\.(\d{1,3}))?$/', $time, $m)) {
            return null;
        }

        $h = ($m[1] ?? '') !== '' ? (int) $m[1] : 0;
        $min = (int) $m[2];
        $sec = (int) $m[3];
        $frac = $m[4] ?? '';

        $ms = ($h * 3600 + $min * 60 + $sec) * 1000;
        $ms += self::fracToMs($frac);

        return $ms;
    }

    private static function fracToMs(string $frac): int
    {
        $frac = trim($frac);
        if ($frac === '') {
            return 0;
        }

        // pad right to 3 digits
        if (strlen($frac) === 1) {
            $frac .= '00';
        }
        if (strlen($frac) === 2) {
            $frac .= '0';
        }
        if (strlen($frac) > 3) {
            $frac = substr($frac, 0, 3);
        }

        return (int) $frac;
    }

    /** Default UI format: MM:SS.xx (centiseconds); if >= 1h -> HH:MM:SS.xx */
    public static function format(?int $ms): string
    {
        if ($ms === null) {
            return '';
        }

        $ms = max(0, (int) $ms);

        $totalSeconds = intdiv($ms, 1000);
        $millis = $ms % 1000;

        // centiseconds for display
        $cs = intdiv($millis, 10);

        $h = intdiv($totalSeconds, 3600);
        $m = intdiv($totalSeconds % 3600, 60);
        $s = $totalSeconds % 60;

        if ($h > 0) {
            return sprintf('%02d:%02d:%02d.%02d', $h, $m, $s, $cs);
        }

        return sprintf('%02d:%02d.%02d', $m, $s, $cs);
    }
}
