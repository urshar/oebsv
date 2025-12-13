<?php

if (!function_exists('format_swim_time')) {
    function format_swim_time(?int $ms): string
    {
        if (!$ms) {
            return '—';
        }
        $minutes = floor($ms / 60000);
        $seconds = floor(($ms % 60000) / 1000);
        $centis = floor(($ms % 1000) / 10);

        return sprintf('%02d:%02d,%02d', $minutes, $seconds, $centis);
    }
}
