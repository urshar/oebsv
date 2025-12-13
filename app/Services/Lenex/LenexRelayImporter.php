<?php

namespace App\Services\Lenex;

use App\Models\ParaAthlete;
use App\Models\ParaClub;
use App\Models\ParaEntry;
use App\Models\ParaEvent;
use App\Models\ParaMeet;
use App\Models\ParaRelayEntry;
use App\Models\ParaRelayLegSplit;
use App\Models\ParaRelayMember;
use App\Models\ParaRelayResult;
use App\Models\ParaRelaySplit;
use Exception;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Schema;
use SimpleXMLElement;
use Throwable;
use ZipArchive;

class LenexRelayImporter
{
    /**
     * Importiert ausgewählte Relay-RESULTs aus einem LENEX File.
     *
     * @param  string  $filePath  Pfad zur hochgeladenen .lef/.lxf/.zip Datei
     * @param  ParaMeet  $meet  Meet im System
     * @param  string[]  $selectedLenexResultIds  Array von LENEX RESULT@resultid (aus Preview)
     * @throws Throwable
     */
    public function import(string $filePath, ParaMeet $meet, array $selectedLenexResultIds): void
    {
        $selectedSet = array_flip(array_values(array_filter($selectedLenexResultIds)));

        if (empty($selectedSet)) {
            throw new RuntimeException('Keine Staffeln für den Import ausgewählt.');
        }

        $root = $this->loadLenexRootFromPath($filePath);

        /** @var SimpleXMLElement|null $meetNode */
        $meetNode = $root->MEETS->MEET[0] ?? null;
        if (!$meetNode instanceof SimpleXMLElement) {
            throw new RuntimeException('Keine MEET-Definition im LENEX gefunden.');
        }

        // Map: LENEX eventid -> ParaEvent (nur Events vom Meet)
        $meet->load('sessions.events.swimstyle');

        $eventByLenexId = [];
        foreach ($meet->sessions as $session) {
            foreach ($session->events as $event) {
                if (!empty($event->lenex_eventid)) {
                    $eventByLenexId[(string) $event->lenex_eventid] = $event;
                }
            }
        }

        // HeatId -> Heat number
        $heatNumberById = $this->buildHeatNumberIndex($meetNode);

        // ResultId → place (Ranking; wenn mehrfach, nehmen wir das kleinste "order")
        $rankByResultId = $this->buildRankingIndex($meetNode);

        DB::transaction(function () use (
            $meetNode,
            $meet,
            $selectedSet,
            $eventByLenexId,
            $heatNumberById,
            $rankByResultId
        ) {
            foreach (($meetNode->CLUBS->CLUB ?? []) as $clubNode) {
                /** @var SimpleXMLElement $clubNode */
                $lenexClubId = (string) ($clubNode['clubid'] ?? '');
                $clubName = trim((string) ($clubNode['name'] ?? ''));

                // Club muss im System existieren (wir legen NICHT an)
                $club = $this->findExistingClub($lenexClubId, $clubName);
                if (!$club) {
                    // Club unbekannt → überspringen (Preview soll diese ohnehin rot machen)
                    continue;
                }

                // LENEX-Athletes dieses Clubs indexieren (Club-Zugehörigkeit im LENEX prüfen)
                $athNodeById = [];
                foreach (($clubNode->ATHLETES->ATHLETE ?? []) as $athNode) {
                    $aid = (string) ($athNode['athleteid'] ?? '');
                    if ($aid !== '') {
                        $athNodeById[$aid] = $athNode;
                    }
                }

                foreach (($clubNode->RELAYS->RELAY ?? []) as $relayNode) {
                    /** @var SimpleXMLElement $relayNode */
                    $relayNumber = (string) ($relayNode['number'] ?? null);
                    $relayGender = (string) ($relayNode['gender'] ?? null);

                    foreach (($relayNode->RESULTS->RESULT ?? []) as $resultNode) {
                        /** @var SimpleXMLElement $resultNode */
                        $lenexResultId = (string) ($resultNode['resultid'] ?? '');

                        if ($lenexResultId === '' || !isset($selectedSet[$lenexResultId])) {
                            continue;
                        }

                        $lenexEventId = (string) ($resultNode['eventid'] ?? '');
                        /** @var ParaEvent|null $event */
                        $event = $lenexEventId !== '' ? ($eventByLenexId[$lenexEventId] ?? null) : null;

                        if (!$event) {
                            throw new RuntimeException("Relay Result {$lenexResultId}: Event {$lenexEventId} nicht im Meeting vorhanden.");
                        }
                        if (empty($event->is_relay)) {
                            throw new RuntimeException("Relay Result {$lenexResultId}: Event {$event->id} ist kein Relay-Event.");
                        }

                        // Leg distance & relaycount kommen aus LENEX EVENT/SWIMSTYLE
                        [$legDistance, $relayCount, $eventStroke] = $this->readRelayMetaFromLenexMeet($meetNode,
                            $lenexEventId);
                        if ($legDistance <= 0 || $relayCount <= 0) {
                            throw new RuntimeException("Relay Result {$lenexResultId}: Konnte distance/relaycount nicht aus LENEX bestimmen.");
                        }

                        $totalDistance = $legDistance * $relayCount;

                        // Entry + Result Zeiten
                        $entryTimeStr = (string) ($resultNode['entrytime'] ?? '');
                        $entryTimeMs = $this->parseTimeToMs($entryTimeStr);

                        $swimTimeStr = (string) ($resultNode['swimtime'] ?? '');
                        $swimTimeMs = $this->parseTimeToMs($swimTimeStr);

                        $heatId = (string) ($resultNode['heatid'] ?? '');
                        $heatNumber = $heatId !== '' ? ($heatNumberById[$heatId] ?? null) : null;

                        $rank = $rankByResultId[$lenexResultId] ?? null;

                        // 1) RelayEntry upsert
                        $relayEntry = ParaRelayEntry::updateOrCreate(
                            [
                                'para_meet_id' => $meet->id,
                                'para_event_id' => $event->id,
                                'para_club_id' => $club->id,
                                'lenex_relay_number' => $relayNumber,
                            ],
                            [
                                'para_session_id' => $event->para_session_id ?? null,
                                'lenex_eventid' => $lenexEventId,
                                'lenex_clubid' => $lenexClubId ?: null,
                                'gender' => $relayGender ?: null,
                                'entry_time' => $entryTimeStr ?: null,
                                'entry_time_ms' => $entryTimeMs,
                            ]
                        );

                        // 2) RelayResult upsert
                        $relayResult = ParaRelayResult::updateOrCreate(
                            [
                                'para_relay_entry_id' => $relayEntry->id,
                                'para_meet_id' => $meet->id,
                            ],
                            [
                                'time_ms' => $swimTimeMs,
                                'rank' => $rank,
                                'heat' => $heatNumber,
                                'lane' => isset($resultNode['lane']) ? (int) $resultNode['lane'] : null,
                                'status' => (string) ($resultNode['status'] ?? 'OK'),
                                'points' => isset($resultNode['points']) ? (int) $resultNode['points'] : null,
                                'lenex_resultid' => $lenexResultId ?: null,
                                'lenex_heatid' => $heatId ?: null,
                            ]
                        );

                        // 3) Members (Leg 1..n) – zuerst löschen (oder updateOrCreate je nach Geschmack)
                        ParaRelayMember::where('para_relay_entry_id', $relayEntry->id)->delete();

                        // Leg -> ParaRelayMember
                        $memberByLeg = [];

                        foreach (($resultNode->RELAYPOSITIONS->RELAYPOSITION ?? []) as $posNode) {
                            /** @var SimpleXMLElement $posNode */
                            $lenexAthleteId = (string) ($posNode['athleteid'] ?? '');
                            $leg = isset($posNode['number']) ? (int) $posNode['number'] : null;

                            if (!$leg || $lenexAthleteId === '') {
                                throw new RuntimeException("Relay Result {$lenexResultId}: Ungültige RELAYPOSITION (leg/athleteid fehlt).");
                            }

                            // Muss in ATHLETES des Clubs im LENEX vorkommen
                            if (!isset($athNodeById[$lenexAthleteId])) {
                                throw new RuntimeException("Relay Result {$lenexResultId}: Athlete {$lenexAthleteId} gehört im LENEX nicht zu Club {$clubName}.");
                            }

                            $athNode = $athNodeById[$lenexAthleteId];

                            // Athlete im System finden
                            $dbAthlete = $this->findAthleteInDb($lenexAthleteId, $athNode);
                            if (!$dbAthlete) {
                                throw new RuntimeException("Relay Result {$lenexResultId}: Athlete {$lenexAthleteId} nicht in para_athletes gefunden.");
                            }

                            // Athlete muss im System dem Club zugeordnet sein
                            if ((int) $dbAthlete->para_club_id !== (int) $club->id) {
                                throw new RuntimeException("Relay Result {$lenexResultId}: Athlete {$dbAthlete->id} ist im System bei anderem Club.");
                            }

                            $member = ParaRelayMember::create([
                                'para_relay_entry_id' => $relayEntry->id,
                                'para_athlete_id' => $dbAthlete->id,
                                'leg' => $leg,
                                'lenex_athleteid' => $lenexAthleteId,
                                'leg_time_ms' => null, // wird unten gesetzt
                                'leg_distance' => $legDistance,
                                'leg_stroke' => $this->resolveLegStroke($eventStroke, $leg, $relayCount),
                            ]);

                            $memberByLeg[$leg] = $member;
                        }

                        // 4) Team-Splits (roh) speichern + finalen Split aus RESULT@swimtime ergänzen
                        ParaRelaySplit::where('para_relay_result_id', $relayResult->id)->delete();

                        $teamCum = $this->persistTeamSplitsAndBuildCumulativeIndex(
                            $relayResult->id,
                            $resultNode,
                            $totalDistance,
                            $swimTimeStr,
                            $swimTimeMs
                        );

                        // 5) Leg-Splits (pro Athlete/Leg) berechnen & speichern + leg_time_ms setzen
                        // Löschen (falls reimport)
                        foreach ($memberByLeg as $m) {
                            ParaRelayLegSplit::where('para_relay_member_id', $m->id)->delete();
                        }

                        $this->persistLegSplitsAndSetLegTimes(
                            $memberByLeg,
                            $teamCum,
                            $legDistance,
                            $relayCount
                        );
                    }
                }
            }
        });
    }

    // ------------------------------------------------------------
    // LENEX Reading helpers
    // ------------------------------------------------------------

    /**
     * @throws Exception
     */
    private function loadLenexRootFromPath(string $filePath): SimpleXMLElement
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("LENEX Datei nicht gefunden: {$filePath}");
        }

        $lower = strtolower($filePath);

        // .lxf / .zip sind typischerweise ZipArchive mit einer .lef drin
        if (str_ends_with($lower, '.lxf') || str_ends_with($lower, '.zip')) {
            $zip = new ZipArchive();
            if ($zip->open($filePath) !== true) {
                throw new RuntimeException("Konnte ZIP/LXF nicht öffnen: {$filePath}");
            }

            $lefName = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name && str_ends_with(strtolower($name), '.lef')) {
                    $lefName = $name;
                    break;
                }
            }
            if (!$lefName) {
                $zip->close();
                throw new RuntimeException("ZIP/LXF enthält keine .lef Datei: {$filePath}");
            }

            $xml = $zip->getFromName($lefName);
            $zip->close();

            if ($xml === false || trim($xml) === '') {
                throw new RuntimeException("Konnte .lef Inhalt nicht lesen: {$lefName}");
            }

            return new SimpleXMLElement($xml);
        }

        // .lef / .xml
        $xml = file_get_contents($filePath);
        if ($xml === false || trim($xml) === '') {
            throw new RuntimeException("Konnte LENEX Datei nicht lesen: {$filePath}");
        }

        return new SimpleXMLElement($xml);
    }

    private function buildHeatNumberIndex(SimpleXMLElement $meetNode): array
    {
        $map = [];
        foreach (($meetNode->SESSIONS->SESSION ?? []) as $sessionNode) {
            foreach (($sessionNode->EVENTS->EVENT ?? []) as $eventNode) {
                foreach (($eventNode->HEATS->HEAT ?? []) as $heatNode) {
                    $heatId = (string) ($heatNode['heatid'] ?? '');
                    if ($heatId !== '') {
                        $map[$heatId] = (int) ($heatNode['number'] ?? 0);
                    }
                }
            }
        }
        return $map;
    }

    private function buildRankingIndex(SimpleXMLElement $meetNode): array
    {
        $map = []; // resultid => place
        $bestOrder = []; // resultid => min order

        foreach (($meetNode->SESSIONS->SESSION ?? []) as $sessionNode) {
            foreach (($sessionNode->EVENTS->EVENT ?? []) as $eventNode) {
                foreach (($eventNode->AGEGROUPS->AGEGROUP ?? []) as $agNode) {
                    foreach (($agNode->RANKINGS->RANKING ?? []) as $rankingNode) {
                        $resultId = (string) ($rankingNode['resultid'] ?? '');
                        if ($resultId === '') {
                            continue;
                        }
                        $order = (int) ($rankingNode['order'] ?? 999999);
                        $place = isset($rankingNode['place']) ? (int) $rankingNode['place'] : null;

                        if (!isset($bestOrder[$resultId]) || $order < $bestOrder[$resultId]) {
                            $bestOrder[$resultId] = $order;
                            $map[$resultId] = $place;
                        }
                    }
                }
            }
        }

        return $map;
    }

    private function findExistingClub(string $lenexClubId, string $clubName): ?ParaClub
    {
        // Wenn du in para_clubs eine Spalte lenex_clubid hast -> hier als erstes verwenden.
        // Sonst fallback: Name match.
        $q = ParaClub::query();

        if ($lenexClubId !== '' && $this->columnExists('para_clubs', 'lenex_clubid')) {
            $club = (clone $q)->where('lenex_clubid', $lenexClubId)->first();
            if ($club) {
                return $club;
            }
        }

        if ($clubName === '') {
            return null;
        }

        // Fallback: Name match (passe ggf. Feldnamen an dein ParaClub Model an)
        return $q->where(function ($qq) use ($clubName) {
            $qq->where('nameDe', $clubName)
                ->orWhere('shortNameDe', $clubName)
                ->orWhere('nameEn', $clubName)
                ->orWhere('shortNameEn', $clubName);
        })->first();
    }

    // ------------------------------------------------------------
    // Mapping helpers (Club/Athlete)
    // ------------------------------------------------------------

    private function columnExists(string $table, string $column): bool
    {
        // schnelle & DB-agnostische Lösung für Laravel:
        try {
            return Schema::hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Liest aus LENEX die SWIMSTYLE meta für eventid:
     * - distance (pro Leg!)
     * - relaycount
     * - stroke
     *
     * @return array{0:int,1:int,2:string|null}
     */
    private function readRelayMetaFromLenexMeet(SimpleXMLElement $meetNode, string $lenexEventId): array
    {
        foreach (($meetNode->SESSIONS->SESSION ?? []) as $sessionNode) {
            foreach (($sessionNode->EVENTS->EVENT ?? []) as $eventNode) {
                if ((string) ($eventNode['eventid'] ?? '') !== $lenexEventId) {
                    continue;
                }
                $ss = $eventNode->SWIMSTYLE ?? null;
                if (!$ss instanceof SimpleXMLElement) {
                    return [0, 0, null];
                }

                $distance = (int) ($ss['distance'] ?? 0);        // pro Leg
                $relaycount = (int) ($ss['relaycount'] ?? 0);
                $stroke = (string) ($ss['stroke'] ?? '');

                return [$distance, $relaycount, $stroke ?: null];
            }
        }
        return [0, 0, null];
    }

    // ------------------------------------------------------------
    // Splits logic
    // ------------------------------------------------------------

    /**
     * Parse LENEX time strings like:
     * 00:02:29.49  (centiseconds)
     * 00:00:33,72  (comma)
     * 00:00:33.720 (milliseconds)
     */
    private function parseTimeToMs(?string $time): ?int
    {
        $time = trim((string) $time);
        if ($time === '' || $time === 'NT') {
            return null;
        }

        // normalize comma to dot
        $time = str_replace(',', '.', $time);

        // H:M:S.F
        if (!preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{2})(?:\.(\d{1,3}))?$/', $time, $m)) {
            return null;
        }

        $h = isset($m[1]) && $m[1] !== '' ? (int) $m[1] : 0;
        $min = (int) $m[2];
        $sec = (int) $m[3];
        $frac = $m[4] ?? '';

        $ms = ($h * 3600 + $min * 60 + $sec) * 1000;

        if ($frac !== '') {
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
            $ms += (int) $frac;
        }

        return $ms;
    }

    private function findAthleteInDb(string $lenexAthleteId, SimpleXMLElement $athNode): ?ParaAthlete
    {
        // 1) Prefer: Mapping über ParaEntry.lenex_athleteid (wenn vorhanden)
        if ($this->columnExists('para_entries', 'lenex_athleteid')) {
            $entry = ParaEntry::query()
                ->where('lenex_athleteid', $lenexAthleteId)
                ->with('athlete')
                ->first();

            if ($entry?->athlete) {
                return $entry->athlete;
            }
        }

        // 2) Fallback: match by name (+ optional birthdate)
        $first = trim((string) ($athNode['firstname'] ?? $athNode['givenname'] ?? ''));
        $last = trim((string) ($athNode['lastname'] ?? $athNode['familyname'] ?? ''));
        $birthdate = trim((string) ($athNode['birthdate'] ?? ''));

        if ($first === '' || $last === '') {
            return null;
        }

        $q = ParaAthlete::query()
            // passe ggf. Feldnamen an (firstName/lastName in deinem Model)
            ->whereRaw('LOWER("firstName") = LOWER(?)', [$first])
            ->whereRaw('LOWER("lastName") = LOWER(?)', [$last]);

        if ($birthdate !== '') {
            $q->whereDate('birthdate', $birthdate);
        }

        return $q->first();
    }

    // ------------------------------------------------------------
    // Time parsing + misc
    // ------------------------------------------------------------

    private function resolveLegStroke(?string $eventStroke, int $leg, int $relayCount): ?string
    {
        if (!$eventStroke) {
            return null;
        }

        $stroke = strtoupper($eventStroke);

        // Standard Medley mapping (4 legs)
        if ($stroke === 'MEDLEY' && $relayCount === 4) {
            return match ($leg) {
                1 => 'BACK',
                2 => 'BREAST',
                3 => 'FLY',
                4 => 'FREE',
                default => 'MEDLEY',
            };
        }

        return $stroke;
    }

    /**
     * Speichert Team-Splits (roh) und gibt ein cumulative-index zurück:
     * distance => cumulative_time_ms
     *
     * WICHTIG:
     * LENEX liefert oft nur Zwischenpunkte (z.B. 50/100/150) und die Endzeit nur als RESULT@swimtime.
     * Daher ergänzen wir immer den finalen Punkt (totalDistance).
     *
     * @return array<int,int> distance => cumulative_time_ms
     */
    private function persistTeamSplitsAndBuildCumulativeIndex(
        int $relayResultId,
        SimpleXMLElement $resultNode,
        int $totalDistance,
        string $finalSwimTimeStr,
        ?int $finalSwimTimeMs
    ): array {
        $cum = [];

        // aus <SPLITS><SPLIT distance=".." swimtime=".."/></SPLITS>
        $splitNodes = $resultNode->SPLITS->SPLIT ?? [];
        $points = [];

        foreach ($splitNodes as $sp) {
            $d = (int) ($sp['distance'] ?? 0);
            $tStr = (string) ($sp['swimtime'] ?? '');
            $tMs = $this->parseTimeToMs($tStr);

            if ($d > 0 && $tMs !== null) {
                $points[$d] = ['ms' => $tMs, 'raw' => $tStr];
            }
        }

        // finalen Punkt ergänzen
        if ($totalDistance > 0 && $finalSwimTimeMs !== null) {
            $points[$totalDistance] = ['ms' => $finalSwimTimeMs, 'raw' => $finalSwimTimeStr];
        }

        if (empty($points)) {
            return [];
        }

        ksort($points);

        $prevMs = 0;
        $prevDist = 0;

        foreach ($points as $dist => $data) {
            $ms = $data['ms'];
            $raw = $data['raw'];

            $splitMs = null;
            if ($dist > $prevDist) {
                $splitMs = $ms - $prevMs;
            }

            ParaRelaySplit::create([
                'para_relay_result_id' => $relayResultId,
                'distance' => $dist,
                'cumulative_time_ms' => $ms,
                'split_time_ms' => $splitMs,
                'lenex_swimtime' => $raw ?: null,
            ]);

            $cum[$dist] = $ms;
            $prevMs = $ms;
            $prevDist = $dist;
        }

        // Startpunkt 0 implizit
        $cum[0] = 0;

        return $cum;
    }

    /**
     * Leitet aus Team-cumulative (ab Start) die Leg-Splits (pro Member) ab.
     * Setzt außerdem member.leg_time_ms.
     *
     * @param  array<int,ParaRelayMember>  $memberByLeg
     * @param  array<int,int>  $teamCum  distance => cumulative_time_ms
     */
    private function persistLegSplitsAndSetLegTimes(
        array $memberByLeg,
        array $teamCum,
        int $legDistance,
        int $relayCount
    ): void {
        if ($legDistance <= 0 || $relayCount <= 0) {
            return;
        }

        // Sortierte Distanzen
        $distances = array_keys($teamCum);
        sort($distances);

        // Helper: nearest cum time for exact distance (idealerweise immer vorhanden für Leg-Enden)
        $getTime = function (int $distance) use ($teamCum): ?int {
            return $teamCum[$distance] ?? null;
        };

        for ($leg = 1; $leg <= $relayCount; $leg++) {
            $member = $memberByLeg[$leg] ?? null;
            if (!$member) {
                // falls LENEX weniger/more positions liefert -> hart, damit Preview fixen kann
                throw new RuntimeException("Relay: Missing member for leg {$leg}.");
            }

            $startDist = ($leg - 1) * $legDistance;
            $endDist = $leg * $legDistance;

            $startTime = $getTime($startDist);
            $endTime = $getTime($endDist);

            // startTime ist bei leg>1 normalerweise das Ende der vorherigen Leg
            if ($startTime === null) {
                // fallback: best effort -> größte bekannte Distanz < startDist
                $startTime = 0;
                foreach ($distances as $d) {
                    if ($d < $startDist) {
                        $startTime = $teamCum[$d];
                    }
                }
            }

            if ($endTime === null) {
                // fallback: größte bekannte Distanz <= endDist
                $endTime = null;
                foreach ($distances as $d) {
                    if ($d <= $endDist) {
                        $endTime = $teamCum[$d];
                    }
                }
            }

            if ($endTime === null) {
                // ohne Endzeit können wir keine Leg-Zeit berechnen
                continue;
            }

            // Leg-Endzeit setzen (wichtig für Rekorde + schnelle Queries)
            $member->update([
                'leg_time_ms' => $endTime - $startTime,
            ]);

            // Leg-splits: alle Team-splitpunkte innerhalb (startDist, endDist]
            $prevLegCum = 0;

            foreach ($distances as $absDist) {
                if ($absDist <= $startDist) {
                    continue;
                }
                if ($absDist > $endDist) {
                    break;
                }

                $absTime = $teamCum[$absDist];
                $legCum = $absTime - $startTime;
                $distInLeg = $absDist - $startDist;

                $splitMs = $legCum - $prevLegCum;

                ParaRelayLegSplit::create([
                    'para_relay_member_id' => $member->id,
                    'distance_in_leg' => $distInLeg,
                    'cumulative_time_ms' => $legCum,
                    'split_time_ms' => $splitMs,
                    'absolute_distance' => $absDist,
                ]);

                $prevLegCum = $legCum;
            }
        }
    }
}
