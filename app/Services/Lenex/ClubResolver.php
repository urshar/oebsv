<?php

namespace App\Services\Lenex;

use App\Models\ParaClub;
use App\Models\Nation;
use SimpleXMLElement;

class ClubResolver
{
    public function __construct(
        protected NationResolver $nationResolver,
    ) {}

    /**
     * Findet oder erstellt einen ParaClub aus einem LENEX-<CLUB>-Knoten.
     *
     * Erwartete Felder:
     * - @swrid (optional)
     * - @code  (optional, Verbands-Code)
     * - @nation (IOC/ISO3, z.B. AUT)
     * - @name  (Clubname)
     */
    public function resolveFromLenex(SimpleXMLElement $clubNode): ParaClub
    {
        $nation = $this->nationResolver->fromLenexCode((string) $clubNode['nation']);
        $nationId = $nation?->id;

        $swrid = trim((string) ($clubNode['swrid'] ?? ''));
        $code  = trim((string) ($clubNode['code'] ?? ''));
        $name  = trim((string) $clubNode['name']);

        // 1. nach swrid suchen
        if ($swrid !== '') {
            $existing = ParaClub::where('swrid', $swrid)->first();
            if ($existing) {
                return $existing;
            }
        }

        // 2. nach (clubCode + nation) suchen
        if ($code !== '' && $nationId) {
            $existing = ParaClub::where('clubCode', $code)
                ->where('nation_id', $nationId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // 3. nach (nameDe + nation) suchen
        if ($nationId) {
            $existing = ParaClub::where('nameDe', $name)
                ->where('nation_id', $nationId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // 4. neu anlegen
        return ParaClub::create([
            'swrid'     => $swrid !== '' ? $swrid : null,
            'clubCode'  => $code !== '' ? $code : null,
            'nameDe'    => $name,
            // nameEn kannst du bei Bedarf später setzen / übersetzen
            'nation_id' => $nationId,
        ]);
    }

    /**
     * Optional: Resolver für Clubs aus dem Frontend (kein SimpleXMLElement).
     */
    public function resolveFromData(
        string $nameDe,
        ?Nation $nation = null,
        ?string $clubCode = null
    ): ParaClub {
        $nationId = $nation?->id;

        // 1. Code + Nation
        if ($clubCode && $nationId) {
            $existing = ParaClub::where('clubCode', $clubCode)
                ->where('nation_id', $nationId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // 2. Name + Nation
        if ($nationId) {
            $existing = ParaClub::where('nameDe', $nameDe)
                ->where('nation_id', $nationId)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // 3. neu anlegen
        return ParaClub::create([
            'nameDe'    => $nameDe,
            'clubCode'  => $clubCode,
            'nation_id' => $nationId,
        ]);
    }
}
