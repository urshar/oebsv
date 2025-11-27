<?php

namespace App\Services\Lenex;

use App\Models\Nation;

class NationResolver
{
    /**
     * Versucht, einen LENEX-Nationscode (meist IOC oder ISO3, z.B. "AUT")
     * auf einen Nation-Datensatz zu mappen.
     *
     * @param  string|null  $code
     * @return Nation|null
     */
    public function fromLenexCode(?string $code): ?Nation
    {
        if (!$code) {
            return null;
        }

        $code = strtoupper(trim($code));

        // 1. Versuch: ISO3
        $nation = Nation::where('iso3', $code)->first();
        if ($nation) {
            return $nation;
        }

        // 2. Versuch: IOC
        $nation = Nation::where('ioc', $code)->first();
        if ($nation) {
            return $nation;
        }

        // 3. Versuch: ISO2 (falls LENEX dort mal "AT", "DE" etc. liefert)
        $nation = Nation::where('iso2', $code)->first();
        if ($nation) {
            return $nation;
        }

        // 4. Fallback: nameEn (falls du irgendwann volle Ländernamen importierst)
        return Nation::where('nameEn', $code)->first();
    }
}
