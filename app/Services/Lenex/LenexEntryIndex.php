<?php

namespace App\Services\Lenex;

use App\Models\ParaEntry;

final class LenexEntryIndex
{
    /**
     * Optionaler “Bundle”-Index, damit Services nicht mehrere Loops brauchen.
     *
     * @param  iterable<ParaEntry>  $entries
     * @return array{
     *   byAthleteEvent: array<string, ParaEntry>,
     *   byDbAthleteEvent: array<string, ParaEntry>
     * }
     */
    public static function buildIndex(iterable $entries): array
    {
        return [
            'byAthleteEvent' => self::byAthleteEvent($entries),
            'byDbAthleteEvent' => self::byDbAthleteEvent($entries),
        ];
    }

    /**
     * key = "{lenex_athleteid}|{lenex_eventid}"
     *
     * @param  iterable<ParaEntry>  $entries
     * @return array<string, ParaEntry>
     */
    public static function byAthleteEvent(iterable $entries): array
    {
        $map = [];

        foreach ($entries as $e) {
            $aid = (string) ($e->lenex_athleteid ?? '');
            $eid = (string) ($e->lenex_eventid ?? '');

            if ($aid !== '' && $eid !== '') {
                $map[$aid.'|'.$eid] = $e;
            }
        }

        return $map;
    }

    /**
     * key = "{para_athlete_id}|{para_event_id}"
     *
     * @param  iterable<ParaEntry>  $entries
     * @return array<string, ParaEntry>
     */
    public static function byDbAthleteEvent(iterable $entries): array
    {
        $map = [];

        foreach ($entries as $e) {
            $aid = (int) ($e->para_athlete_id ?? 0);
            $eid = (int) ($e->para_event_id ?? 0);

            if ($aid > 0 && $eid > 0) {
                $map[$aid.'|'.$eid] = $e;
            }
        }

        return $map;
    }
}
