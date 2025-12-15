<?php

namespace App\Services\Lenex;

use App\Models\ParaEntry;

class LenexEntryIndex
{
    /**
     * @param  iterable<ParaEntry>  $entries
     * @return array<string, ParaEntry> key = "{lenex_athleteid}|{lenex_eventid}"
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
}
