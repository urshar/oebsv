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
use App\Support\SwimTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class LenexRelayImporter
{
    public function __construct(
        protected LenexImportService $lenexImportService
    ) {
    }

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

        // ✅ zentraler Loader aus deinem LenexImportService
        $root = $this->lenexImportService->loadLenexRootFromPath($filePath);

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

        // ResultId -> place (Ranking; wenn mehrfach, nehmen wir das kleinste "order")
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

                // Club muss im System existieren (wir legen hier NICHT an)
                $club = ParaClub::findByLenexOrName($lenexClubId, $clubName);

                if (!$club) {
                    // Preview sollte diese sowieso rot markieren; beim Import überspringen wir.
                    continue;
                }

                // LENEX-Athletes dieses Clubs indexieren (LENEX-Clubzugehörigkeit prüfen)
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

                        // Auswahlfilter
                        if ($lenexResultId === '' || !isset($selectedSet[$lenexResultId])) {
                            continue;
                        }

                        $lenexEventId = (string) ($resultNode['eventid'] ?? '');
                        /** @var ParaEvent|null $event */
                        $event = $lenexEventId !== '' ? ($eventByLenexId[$lenexEventId] ?? null) : null;

                        if (!$event) {
                            throw new RuntimeException("Relay Result {$lenexResultId}: Event {$lenexEventId} nicht im Meeting vorhanden.");
                        }

                        // Leg distance & relaycount kommen aus LENEX (nicht von DB abhängen)
                        [$legDistance, $relayCount, $eventStroke] = $this->readRelayMetaFromLenexMeet($meetNode,
                            $lenexEventId);

                        if ($relayCount <= 1) {
                            throw new RuntimeException("Relay Result {$lenexResultId}: Event {$lenexEventId} ist kein Relay (relaycount<=1).");
                        }
                        if ($legDistance <= 0) {
                            throw new RuntimeException("Relay Result {$lenexResultId}: Konnte distance nicht aus LENEX bestimmen.");
                        }

                        $totalDistance = $legDistance * $relayCount;

                        // Entry/Swimtime
                        $entryTimeStr = (string) ($resultNode['entrytime'] ?? '');
                        $entryTimeMs = SwimTime::parseToMs($entryTimeStr);

                        $swimTimeStr = (string) ($resultNode['swimtime'] ?? '');
                        $swimTimeMs = SwimTime::parseToMs($swimTimeStr);

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
                                'lenex_eventid' => $lenexEventId ?: null,
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

                        // 3) Members (Leg 1..n) – reimport-sicher: löschen & neu
                        ParaRelayMember::where('para_relay_entry_id', $relayEntry->id)->delete();

                        $memberByLeg = [];

                        $positions = $resultNode->RELAYPOSITIONS->RELAYPOSITION ?? [];
                        if (count($positions) === 0) {
                            throw new RuntimeException("Relay Result {$lenexResultId}: Keine RELAYPOSITIONS gefunden.");
                        }

                        foreach ($positions as $posNode) {
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
                                'leg_time_ms' => null, // setzen wir unten aus Splits
                                'leg_distance' => $legDistance,
                                'leg_stroke' => $this->resolveLegStroke($eventStroke, $leg, $relayCount),
                            ]);

                            $memberByLeg[$leg] = $member;
                        }

                        // 4) Team-Splits speichern + cumulative-index bauen (inkl. final time)
                        ParaRelaySplit::where('para_relay_result_id', $relayResult->id)->delete();

                        $teamCum = $this->persistTeamSplitsAndBuildCumulativeIndex(
                            $relayResult->id,
                            $resultNode,
                            $totalDistance,
                            $swimTimeStr,
                            $swimTimeMs
                        );

                        // 5) Leg-Splits pro Member ableiten & leg_time_ms setzen
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
    // LENEX helpers
    // ------------------------------------------------------------

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
        $map = [];      // resultid => place
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

    // ------------------------------------------------------------
    // Mapping helpers (Club/Athlete)
    // ------------------------------------------------------------

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

                $distance = (int) ($ss['distance'] ?? 0);     // pro Leg
                $relaycount = (int) ($ss['relaycount'] ?? 0);
                $stroke = (string) ($ss['stroke'] ?? '');

                return [$distance, $relaycount, $stroke ?: null];
            }
        }
        return [0, 0, null];
    }

    private function findAthleteInDb(string $lenexAthleteId, SimpleXMLElement $athNode): ?ParaAthlete
    {
        // 1) Prefer: Mapping über ParaEntry.lenex_athleteid (exists in deinem Projekt)
        if (Schema::hasColumn('para_entries', 'lenex_athleteid')) {
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
            ->whereRaw('LOWER(firstName) = ?', [mb_strtolower($first)])
            ->whereRaw('LOWER(lastName) = ?', [mb_strtolower($last)]);

        if ($birthdate !== '') {
            $q->whereDate('birthdate', $birthdate);
        }

        return $q->first();
    }

    // ------------------------------------------------------------
    // Splits logic
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
     * LENEX liefert oft nur Zwischenpunkte (z.B. 50/100/150) und Endzeit als RESULT@swimtime.
     * Daher ergänzen wir immer den finalen Punkt (totalDistance), wenn swimtime vorhanden ist.
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
        $points = [];

        // <SPLITS><SPLIT distance=".." swimtime=".."/></SPLITS>
        $splitNodes = $resultNode->SPLITS->SPLIT ?? [];
        foreach ($splitNodes as $sp) {
            $d = (int) ($sp['distance'] ?? 0);
            $tStr = (string) ($sp['swimtime'] ?? '');
            $tMs = SwimTime::parseToMs($tStr);

            if ($d > 0 && $tMs !== null) {
                $points[$d] = ['ms' => $tMs, 'raw' => $tStr];
            }
        }

        // finalen Punkt ergänzen
        if ($totalDistance > 0 && $finalSwimTimeMs !== null) {
            $points[$totalDistance] = ['ms' => $finalSwimTimeMs, 'raw' => $finalSwimTimeStr];
        }

        if (empty($points)) {
            // trotzdem Startpunkt definieren, damit Leg-Calc nicht crasht
            return [0 => 0];
        }

        ksort($points);

        $prevMs = 0;
        $prevDist = 0;

        // Startpunkt 0
        $cum[0] = 0;

        foreach ($points as $dist => $data) {
            $ms = $data['ms'];
            $raw = $data['raw'];

            $splitMs = null;
            if ($dist > $prevDist) {
                $splitMs = $ms - $prevMs;
                if ($splitMs < 0) {
                    $splitMs = null;
                }
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

        $distances = array_keys($teamCum);
        sort($distances);

        $getTime = fn(int $distance) => $teamCum[$distance] ?? null;

        for ($leg = 1; $leg <= $relayCount; $leg++) {
            $member = $memberByLeg[$leg] ?? null;
            if (!$member) {
                throw new RuntimeException("Relay: Missing member for leg {$leg}.");
            }

            $startDist = ($leg - 1) * $legDistance;
            $endDist = $leg * $legDistance;

            $startTime = $getTime($startDist);
            $endTime = $getTime($endDist);

            // startTime fallback: größte Distanz < startDist
            if ($startTime === null) {
                $startTime = 0;
                foreach ($distances as $d) {
                    if ($d < $startDist) {
                        $startTime = $teamCum[$d];
                    }
                }
            }

            // endTime fallback: größte Distanz <= endDist
            if ($endTime === null) {
                $endTime = null;
                foreach ($distances as $d) {
                    if ($d <= $endDist) {
                        $endTime = $teamCum[$d];
                    }
                }
            }

            if ($endTime === null) {
                continue; // ohne Endzeit keine Leg-Time
            }

            $member->update([
                'leg_time_ms' => max(0, $endTime - $startTime),
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
                if ($splitMs < 0) {
                    $splitMs = null;
                }

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
