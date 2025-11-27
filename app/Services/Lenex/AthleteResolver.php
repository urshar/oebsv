<?php

namespace App\Services\Lenex;

use App\Models\ParaAthlete;
use App\Models\ParaClub;
use App\Models\Nation;
use SimpleXMLElement;

class AthleteResolver
{
    /**
     * Findet oder erstellt einen ParaAthlete aus einem LENEX-<ATHLETE>-Knoten.
     *
     * Erwartete Felder:
     * - @swrid       (optional, global ID)
     * - @tmid        (optional, externe ID)
     * - @license     (optional, Verbands-Lizenz)
     * - @firstname   (Pflicht)
     * - @lastname    (Pflicht)
     * - @gender      (optional, M/F/X)
     * - @birthdate   (optional, YYYY-MM-DD)
     *
     * Nation und aktueller Club werden i.d.R. aus dem Kontext (Club-Knoten, NationResolver)
     * vom aufrufenden Code übergeben.
     */
    public function resolveFromLenex(
        SimpleXMLElement $athNode,
        ?ParaClub $club,
        ?Nation $nation
    ): ParaAthlete {
        $swrid   = trim((string) ($athNode['swrid'] ?? ''));
        $tmIdRaw = (string) ($athNode['tmid'] ?? '');
        $tmId    = $tmIdRaw !== '' ? (int) $tmIdRaw : null;
        $license = trim((string) ($athNode['license'] ?? ''));

        $first   = trim((string) $athNode['firstname']);
        $last    = trim((string) $athNode['lastname']);
        $gender  = trim((string) ($athNode['gender'] ?? '')) ?: null;
        $birth   = trim((string) ($athNode['birthdate'] ?? '')) ?: null;
        $nationId = $nation?->id;

        // --- HANDICAP / Sportklassifikation aus LENEX ---
        $handicapNode = $athNode->HANDICAP[0] ?? null;

        $classS  = null;
        $classSB = null;
        $classSM = null;
        $classException = null;

        if ($handicapNode instanceof SimpleXMLElement) {
            // typische Attribute in LENEX: free, breast, medley, exception
            $classS  = trim((string) ($handicapNode['free'] ?? '')) ?: null;     // S-Klasse
            $classSB = trim((string) ($handicapNode['breast'] ?? '')) ?: null;   // SB-Klasse
            $classSM = trim((string) ($handicapNode['medley'] ?? '')) ?: null;   // SM-Klasse
            $classException = trim((string) ($handicapNode['exception'] ?? '')) ?: null;
        }

        // 1. swrid
        if ($swrid !== '') {
            $existing = ParaAthlete::where('swrid', $swrid)->first();
            if ($existing) {
                // Club ggf. nachziehen
                if ($club && !$existing->para_club_id) {
                    $existing->para_club_id = $club->id;
                }
                $this->updateClassificationIfNeeded($existing, $classS, $classSB, $classSM, $classException);
                $existing->save();

                return $existing;
            }
        }

        // 2. tmId
        if ($tmId !== null) {
            $existing = ParaAthlete::where('tmId', $tmId)->first();
            if ($existing) {
                if ($club && !$existing->para_club_id) {
                    $existing->para_club_id = $club->id;
                }
                $this->updateClassificationIfNeeded($existing, $classS, $classSB, $classSM, $classException);
                $existing->save();

                return $existing;
            }
        }

        // 3. license (falls stabil benutzt)
        if ($license !== '') {
            $existing = ParaAthlete::where('oebsv_license', $license)->first();
            if ($existing) {
                if ($club && !$existing->para_club_id) {
                    $existing->para_club_id = $club->id;
                }
                $this->updateClassificationIfNeeded($existing, $classS, $classSB, $classSM, $classException);
                $existing->save();

                return $existing;
            }
        }

        // 4. natürlicher Schlüssel:
        //    firstName + lastName + birthdate + gender + nation_id
        if ($birth && $gender && $nationId) {
            $existing = ParaAthlete::where([
                'firstName' => $first,
                'lastName'  => $last,
                'birthdate' => $birth,
                'gender'    => $gender,
                'nation_id' => $nationId,
            ])->first();

            if ($existing) {
                if ($club && !$existing->para_club_id) {
                    $existing->para_club_id = $club->id;
                }
                $this->updateClassificationIfNeeded($existing, $classS, $classSB, $classSM, $classException);
                $existing->save();

                return $existing;
            }
        }

        // 5. neu anlegen
        return ParaAthlete::create([
            'swrid'        => $swrid !== '' ? $swrid : null,
            'tmId'         => $tmId,
            'oebsv_license'      => $license !== '' ? $license : null,
            'firstName'    => $first,
            'lastName'     => $last,
            'gender'       => $gender,
            'birthdate'    => $birth ?: null,
            'para_club_id' => $club?->id,
            'nation_id'    => $nationId,

            // Sportklassen aus HANDICAP
            'sportclass_s'         => $classS,
            'sportclass_sb'        => $classSB,
            'sportclass_sm'        => $classSM,
            'sportclass_exception' => $classException,
        ]);
    }

    /**
     * Optional: Resolver für Athleten aus dem Frontend (ohne SimpleXMLElement).
     */
    public function resolveFromData(
        string $firstName,
        string $lastName,
        ?string $birthdate,
        ?string $gender,
        ?Nation $nation,
        ?ParaClub $club = null,
        ?string $license = null
    ): ParaAthlete {
        $nationId = $nation?->id;

        // Natürlicher Schlüssel, wenn genug Daten da sind
        if ($birthdate && $gender && $nationId) {
            $existing = ParaAthlete::where([
                'firstName' => $firstName,
                'lastName'  => $lastName,
                'birthdate' => $birthdate,
                'gender'    => $gender,
                'nation_id' => $nationId,
            ])->first();

            if ($existing) {
                return $existing;
            }
        }

        // Lizenz-basierte Suche
        if ($license) {
            $existing = ParaAthlete::where('license', $license)->first();
            if ($existing) {
                return $existing;
            }
        }

        // Neu anlegen
        return ParaAthlete::create([
            'firstName'    => $firstName,
            'lastName'     => $lastName,
            'birthdate'    => $birthdate,
            'gender'       => $gender,
            'nation_id'    => $nationId,
            'para_club_id' => $club?->id,
            'license'      => $license,
        ]);
    }

    /**
     * Aktualisiert Sportklassen-Felder eines bestehenden Athleten,
     * falls im LENEX neue Werte vorhanden sind.
     */
    protected function updateClassificationIfNeeded(
        ParaAthlete $athlete,
        ?string $classS,
        ?string $classSB,
        ?string $classSM,
        ?string $classException
    ): void {
        $dirty = false;

        if ($classS && $athlete->sportclass_s !== $classS) {
            $athlete->sportclass_s = $classS;
            $dirty = true;
        }

        if ($classSB && $athlete->sportclass_sb !== $classSB) {
            $athlete->sportclass_sb = $classSB;
            $dirty = true;
        }

        if ($classSM && $athlete->sportclass_sm !== $classSM) {
            $athlete->sportclass_sm = $classSM;
            $dirty = true;
        }

        if ($classException && $athlete->sportclass_exception !== $classException) {
            $athlete->sportclass_exception = $classException;
            $dirty = true;
        }

        if ($dirty) {
            $athlete->save();
        }
    }
}
