<?php

namespace App\Services\Lenex;

use App\Models\ParaAthlete;
use App\Models\ParaClub;
use App\Models\ParaMeet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class LenexRelayImporter
{
    public function __construct(
        protected LenexImportService $lenex,
    ) {
    }

    /**
     * @param  array<string,string|int>  $athleteMatch  athlete_match[lenexAthleteId] => para_athletes.id | "new" | "" | "auto"
     * @param  array<string,string|int>  $clubMatch  club_match[lenexClubId] => para_clubs.id | "new" | "" | "auto"
     * @throws Throwable
     */
    public function import(
        string $path,
        ParaMeet $meet,
        array $selectedResultKeys,
        array $athleteMatch = [],
        array $clubMatch = [],
    ): void {
        $selected = array_fill_keys(array_map('strval', $selectedResultKeys), true);

        $root = $this->lenex->loadLenexRootFromPath($path);
        $meetNode = $root->MEETS->MEET[0] ?? null;
        if (!$meetNode instanceof SimpleXMLElement) {
            throw new RuntimeException('Keine MEET-Definition im LENEX gefunden (Relays).');
        }

        foreach ([
                     'para_relay_entries', 'para_relay_members', 'para_relay_results', 'para_relay_splits',
                     'para_relay_leg_splits'
                 ] as $t) {
            if (!Schema::hasTable($t)) {
                throw new RuntimeException("Tabelle {$t} fehlt. Bitte migrations ausführen.");
            }
        }

        $meet->load('sessions.events.agegroups', 'sessions.events.swimstyle');

        $eventByLenexId = [];
        foreach ($meet->sessions as $session) {
            foreach ($session->events as $event) {
                if (!empty($event->lenex_eventid)) {
                    $eventByLenexId[(string) $event->lenex_eventid] = $event;
                }
            }
        }

        $globalAthNodeById = $this->buildGlobalAthleteIndex($meetNode);
        $resultAgegroupByResultId = $this->buildResultAgegroupIndex($meetNode);

        DB::transaction(function () use (
            $meet,
            $meetNode,
            $selected,
            $eventByLenexId,
            $globalAthNodeById,
            $resultAgegroupByResultId,
            $athleteMatch,
            $clubMatch
        ) {
            foreach (($meetNode->CLUBS->CLUB ?? []) as $clubNode) {
                /** @var SimpleXMLElement $clubNode */
                $nation = trim((string) ($clubNode['nation'] ?? ''));
                $clubName = trim((string) ($clubNode['name'] ?? ''));
                $lenexClubId = trim((string) ($clubNode['clubid'] ?? ''));

                $dbClub = $this->resolveClub($clubNode, $clubMatch);

                // Club-local athletes (LENEX membership check + names)
                $clubAthNodeById = [];
                foreach (($clubNode->ATHLETES->ATHLETE ?? []) as $aNode) {
                    $aid = trim((string) ($aNode['athleteid'] ?? ''));
                    if ($aid !== '') {
                        $clubAthNodeById[$aid] = $aNode;
                    }
                }

                foreach (($clubNode->RELAYS->RELAY ?? []) as $relayNode) {
                    /** @var SimpleXMLElement $relayNode */
                    $relayNumber = trim((string) ($relayNode['number'] ?? ''));
                    $relayGender = trim((string) ($relayNode['gender'] ?? ''));

                    foreach (($relayNode->RESULTS->RESULT ?? []) as $resNode) {
                        /** @var SimpleXMLElement $resNode */
                        $lenexEventId = trim((string) ($resNode['eventid'] ?? ''));
                        $lenexResultId = trim((string) ($resNode['resultid'] ?? ''));

                        // Key-Logik: wir akzeptieren beide Varianten (neu + alt)
                        $fallbackNew = $nation.'|'.$clubName.'|'.$relayNumber.'|'.$lenexEventId;
                        $fallbackOld = $lenexEventId.'|'.$relayNumber.'|'.$clubName;

                        $keyCandidates = array_values(array_filter([
                            $lenexResultId !== '' ? $lenexResultId : null,
                            $fallbackNew,
                            $fallbackOld,
                        ]));

                        $isSelected = false;
                        foreach ($keyCandidates as $k) {
                            if (isset($selected[(string) $k])) {
                                $isSelected = true;
                                break;
                            }
                        }
                        if (!$isSelected) {
                            continue;
                        }

                        $event = $lenexEventId !== '' ? ($eventByLenexId[$lenexEventId] ?? null) : null;
                        if (!$event) {
                            // Preview invalid
                            continue;
                        }

                        // Club darf null sein -> wir legen ihn dann trotzdem an (dbClub kann null nur sein wenn name auch fehlt)
                        if (!$dbClub) {
                            $dbClub = $this->createOrUpdateClubFromLenex(
                                $lenexClubId,
                                $clubName,
                                trim((string) ($clubNode['shortname'] ?? ''))
                            );
                        }

                        // Agegroup optional (für relays oft nur über RANKINGS->resultid)
                        $paraEventAgegroupId = null;
                        $lenexAgegroupId = trim((string) ($resNode['agegroupid'] ?? ''));
                        if ($lenexAgegroupId === '' && $lenexResultId !== '') {
                            $lenexAgegroupId = (string) ($resultAgegroupByResultId[$lenexResultId]['agegroupid'] ?? '');
                        }
                        if ($lenexAgegroupId !== '' && isset($event->agegroups)) {
                            foreach ($event->agegroups as $ag) {
                                if ((string) ($ag->lenex_agegroupid ?? '') === $lenexAgegroupId) {
                                    $paraEventAgegroupId = $ag->id;
                                    break;
                                }
                            }
                        }

                        // Relay Entry upsert
                        $relayEntryWhere = [
                            'para_meet_id' => $meet->id,
                            'para_event_id' => $event->id,
                            'para_club_id' => $dbClub->id,
                            'lenex_relay_number' => $relayNumber !== '' ? $relayNumber : null,
                        ];

                        $relayEntryData = array_filter([
                            'para_meet_id' => $meet->id,
                            'para_session_id' => $event->para_session_id ?? null,
                            'para_event_id' => $event->id,
                            'para_club_id' => $dbClub->id,

                            'lenex_eventid' => $lenexEventId !== '' ? $lenexEventId : null,
                            'lenex_clubid' => $lenexClubId !== '' ? $lenexClubId : null,
                            'lenex_relay_number' => $relayNumber !== '' ? $relayNumber : null,
                            'gender' => $relayGender !== '' ? $relayGender : null,

                            // entries für relays haben selten entry_time im results file
                            'entry_time' => null,
                            'entry_time_ms' => null,
                        ], fn($v) => $v !== null);

                        $relayEntryData = $this->filterColumns('para_relay_entries', $relayEntryData);
                        DB::table('para_relay_entries')->updateOrInsert($relayEntryWhere, $relayEntryData);

                        $relayEntryId = (int) DB::table('para_relay_entries')->where($relayEntryWhere)->value('id');
                        if ($relayEntryId <= 0) {
                            throw new RuntimeException('Konnte para_relay_entries.id nicht ermitteln.');
                        }

                        // Relay Result upsert
                        $swimtimeStr = trim((string) ($resNode['swimtime'] ?? ''));
                        $timeMs = $this->lenex->parseTimeToMs($swimtimeStr);

                        $rank = $this->firstIntAttr($resNode, ['rank', 'place', 'position']);
                        $heat = $this->firstIntAttr($resNode, ['heat']);
                        $lane = $this->firstIntAttr($resNode, ['lane']);
                        $points = $this->firstIntAttr($resNode, ['points', 'point', 'score']);
                        $status = isset($resNode['status']) ? (string) $resNode['status'] : null;

                        $relayResultWhere = [
                            'para_relay_entry_id' => $relayEntryId,
                            'para_meet_id' => $meet->id,
                        ];

                        $relayResultData = array_filter([
                            'para_relay_entry_id' => $relayEntryId,
                            'para_meet_id' => $meet->id,
                            'time_ms' => $timeMs,
                            'rank' => $rank,
                            'heat' => $heat,
                            'lane' => $lane,
                            'status' => $status,
                            'points' => $points,
                            'lenex_resultid' => $lenexResultId !== '' ? $lenexResultId : null,
                            'lenex_heatid' => isset($resNode['heatid']) ? (string) $resNode['heatid'] : null,
                        ], fn($v) => $v !== null);

                        $relayResultData = $this->filterColumns('para_relay_results', $relayResultData);
                        DB::table('para_relay_results')->updateOrInsert($relayResultWhere, $relayResultData);

                        $relayResultId = (int) DB::table('para_relay_results')->where($relayResultWhere)->value('id');
                        if ($relayResultId <= 0) {
                            throw new RuntimeException('Konnte para_relay_results.id nicht ermitteln.');
                        }

                        // Relay splits neu schreiben
                        DB::table('para_relay_splits')->where('para_relay_result_id', $relayResultId)->delete();

                        $splits = [];
                        foreach (($resNode->SPLITS->SPLIT ?? []) as $splitNode) {
                            /** @var SimpleXMLElement $splitNode */
                            $dist = isset($splitNode['distance']) ? (int) $splitNode['distance'] : null;
                            $tStr = trim((string) ($splitNode['swimtime'] ?? ''));
                            $tMs = $this->lenex->parseTimeToMs($tStr);

                            if (!$dist || $tMs === null) {
                                continue;
                            }

                            $splits[] = [
                                'distance' => $dist,
                                'ms' => $tMs,
                                'raw' => $tStr,
                            ];
                        }
                        usort($splits, fn($a, $b) => $a['distance'] <=> $b['distance']);

                        $prevMs = null;
                        foreach ($splits as $sp) {
                            $splitMs = $prevMs !== null ? ($sp['ms'] - $prevMs) : null;

                            $row = [
                                'para_relay_result_id' => $relayResultId,
                                'distance' => $sp['distance'],
                                'cumulative_time_ms' => $sp['ms'],
                                'split_time_ms' => $splitMs,
                                'lenex_swimtime' => $sp['raw'] ?: null,
                            ];
                            $row = $this->filterColumns('para_relay_splits', $row);

                            DB::table('para_relay_splits')->insert($row);
                            $prevMs = $sp['ms'];
                        }

                        // Members + Leg splits
                        $legDistance = $this->guessLegDistance($event, $splits);
                        $positions = $this->iterRelayPositions($relayNode, $resNode);

                        // bestehende members/leg_splits für diesen relayEntry löschen, dann neu
                        $existingMemberIds = DB::table('para_relay_members')->where('para_relay_entry_id',
                            $relayEntryId)->pluck('id')->all();
                        if (!empty($existingMemberIds)) {
                            DB::table('para_relay_leg_splits')->whereIn('para_relay_member_id',
                                $existingMemberIds)->delete();
                        }
                        DB::table('para_relay_members')->where('para_relay_entry_id', $relayEntryId)->delete();

                        $leg = 1;
                        foreach ($positions as $posNode) {
                            /** @var SimpleXMLElement $posNode */
                            $aid = trim((string) ($posNode['athleteid'] ?? ''));
                            $inLenexClub = $aid !== '' && isset($clubAthNodeById[$aid]);

                            $nameNode = $inLenexClub
                                ? ($clubAthNodeById[$aid] ?? null)
                                : ($aid !== '' ? ($globalAthNodeById[$aid] ?? null) : null);

                            $first = $nameNode ? trim((string) ($nameNode['firstname'] ?? $nameNode['givenname'] ?? '')) : '';
                            $last = $nameNode ? trim((string) ($nameNode['lastname'] ?? $nameNode['familyname'] ?? '')) : '';
                            if ($first === '') {
                                $first = 'Unknown';
                            }
                            if ($last === '') {
                                $last = 'Unknown';
                            }

                            $dbAthlete = $this->resolveAthleteFromRelay($aid, $nameNode, $dbClub, $athleteMatch);

                            // Leg time: aus Splits ableiten (wenn möglich)
                            $legTimeMs = null;
                            $legEndAbsDist = ($legDistance !== null) ? ($legDistance * $leg) : null;

                            if ($legEndAbsDist !== null) {
                                $legEndMs = $this->findCumulativeAtDistance($splits, $legEndAbsDist);
                                $legStartMs = $leg === 1 ? 0 : ($this->findCumulativeAtDistance($splits,
                                    $legDistance * ($leg - 1)) ?? 0);

                                if ($legEndMs !== null) {
                                    $legTimeMs = $legEndMs - $legStartMs;
                                }
                            }

                            $memberRow = array_filter([
                                'para_relay_entry_id' => $relayEntryId,
                                'para_athlete_id' => $dbAthlete?->id,
                                'leg' => $leg,
                                'lenex_athleteid' => $aid !== '' ? $aid : null,
                                'leg_time_ms' => $legTimeMs,
                                'leg_distance' => $legDistance,
                                'leg_stroke' => null,
                            ], fn($v) => $v !== null);

                            $memberRow = $this->filterColumns('para_relay_members', $memberRow);
                            $memberId = (int) DB::table('para_relay_members')->insertGetId($memberRow);

                            // Leg splits (optional, best effort)
                            if ($legDistance !== null && $legEndAbsDist !== null) {
                                $this->insertLegSplits($memberId, $splits, $leg, $legDistance);
                            }

                            $leg++;
                        }
                    }
                }
            }
        });
    }

    // ---------- CLUB / ATHLETE RESOLVE ----------

    private function buildGlobalAthleteIndex(SimpleXMLElement $meetNode): array
    {
        $idx = [];
        foreach (($meetNode->CLUBS->CLUB ?? []) as $cNode) {
            foreach (($cNode->ATHLETES->ATHLETE ?? []) as $aNode) {
                $id = trim((string) ($aNode['athleteid'] ?? ''));
                if ($id !== '') {
                    $idx[$id] = $aNode;
                }
            }
        }
        return $idx;
    }

    /**
     * resultid -> ['agegroupid' => '...', 'name' => '...']
     */
    private function buildResultAgegroupIndex(SimpleXMLElement $meetNode): array
    {
        $map = [];
        $bestOrder = [];

        foreach (($meetNode->SESSIONS->SESSION ?? []) as $sessionNode) {
            foreach (($sessionNode->EVENTS->EVENT ?? []) as $eventNode) {
                foreach (($eventNode->AGEGROUPS->AGEGROUP ?? []) as $agNode) {
                    $agegroupid = (string) ($agNode['agegroupid'] ?? '');
                    $name = (string) ($agNode['name'] ?? '');

                    foreach (($agNode->RANKINGS->RANKING ?? []) as $rk) {
                        $rid = (string) ($rk['resultid'] ?? '');
                        if ($rid === '') {
                            continue;
                        }

                        $order = (int) ($rk['order'] ?? 999999);
                        if (!isset($bestOrder[$rid]) || $order < $bestOrder[$rid]) {
                            $bestOrder[$rid] = $order;
                            $map[$rid] = [
                                'agegroupid' => $agegroupid,
                                'name' => $name,
                            ];
                        }
                    }
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<string,string|int>  $clubMatch
     */
    private function resolveClub(SimpleXMLElement $clubNode, array $clubMatch): ?ParaClub
    {
        // identisch zur Results-Variante (kopiert, bewusst ohne extra Service)
        $lenexClubId = trim((string) ($clubNode['clubid'] ?? ''));
        $name = trim((string) ($clubNode['name'] ?? ''));
        $short = trim((string) ($clubNode['shortname'] ?? ''));

        $choice = $lenexClubId !== '' ? ($clubMatch[$lenexClubId] ?? null) : null;
        if (is_string($choice)) {
            $choice = trim($choice);
        }

        if ($choice && $choice !== 'auto') {
            if ($choice === 'new') {
                return $this->createOrUpdateClubFromLenex($lenexClubId, $name, $short);
            }
            if (ctype_digit((string) $choice)) {
                $club = ParaClub::find((int) $choice);
                if ($club) {
                    $this->maybeAttachTmIdToClub($club, $lenexClubId);
                    $club->save();
                    return $club->fresh();
                }
            }
        }

        if ($lenexClubId !== '' && ctype_digit($lenexClubId)) {
            $club = ParaClub::where('tmId', (int) $lenexClubId)->first();
            if ($club) {
                return $club;
            }
        }

        if ($name !== '') {
            $club = ParaClub::query()
                ->where('nameDe', $name)
                ->orWhere('shortNameDe', $name)
                ->orWhere('nameEn', $name)
                ->orWhere('shortNameEn', $name)
                ->orWhere('altNameDe', $name)
                ->orWhere('altShortNameDe', $name)
                ->first();

            if ($club) {
                $this->maybeAttachTmIdToClub($club, $lenexClubId);
                $club->save();
                return $club->fresh();
            }
        }

        return $this->createOrUpdateClubFromLenex($lenexClubId, $name, $short);
    }

    private function createOrUpdateClubFromLenex(string $lenexClubId, string $name, string $short): ParaClub
    {
        $nameDe = $name !== '' ? $name : ('Unbekannter Verein'.($lenexClubId !== '' ? " ({$lenexClubId})" : ''));

        $club = ParaClub::where('nameDe', $nameDe)->first();
        if (!$club) {
            $club = new ParaClub();
            $club->nameDe = $nameDe;
        }

        if ($short !== '' && empty($club->shortNameDe)) {
            $club->shortNameDe = $short;
        }

        $this->maybeAttachTmIdToClub($club, $lenexClubId);

        if (!empty($name) && empty($club->altNameDe) && $club->nameDe !== $name) {
            $club->altNameDe = $name;
        }

        $club->save();
        return $club->fresh();
    }

    private function maybeAttachTmIdToClub(ParaClub $club, string $lenexClubId): void
    {
        if ($lenexClubId === '' || !ctype_digit($lenexClubId)) {
            return;
        }
        if (!empty($club->tmId)) {
            return;
        }

        $tm = (int) $lenexClubId;
        $exists = ParaClub::where('tmId', $tm)->where('id', '!=', $club->id)->exists();
        if ($exists) {
            return;
        }

        $club->tmId = $tm;
    }

    private function filterColumns(string $table, array $data): array
    {
        try {
            $cols = Schema::getColumnListing($table);
        } catch (Throwable) {
            return $data;
        }
        $allowed = array_fill_keys($cols, true);

        return array_filter($data, fn($v, $k) => isset($allowed[$k]), ARRAY_FILTER_USE_BOTH);
    }

    // ---------- Helpers ----------

    private function firstIntAttr(SimpleXMLElement $node, array $attrs): ?int
    {
        foreach ($attrs as $a) {
            if (isset($node[$a]) && (string) $node[$a] !== '') {
                return (int) $node[$a];
            }
        }
        return null;
    }

    private function guessLegDistance($event, array $splits): ?int
    {
        // Primär: Swimstyle (bei Relays i.d.R. distance = Leg-Distanz, relaycount = 4)
        $sw = $event?->swimstyle;
        if ($sw && !empty($sw->relaycount) && (int) $sw->relaycount > 1 && !empty($sw->distance)) {
            return (int) $sw->distance;
        }

        // Fallback: aus dem letzten Split
        if (!empty($splits)) {
            $last = end($splits);
            $lastDist = (int) ($last['distance'] ?? 0);
            if ($lastDist > 0 && $lastDist % 4 === 0) {
                return (int) ($lastDist / 4);
            }
        }

        return null;
    }

    /**
     * RELAYPOSITIONS (Results) oder POSITIONS (Relay) -> array<SimpleXMLElement>
     */
    private function iterRelayPositions(SimpleXMLElement $relayNode, SimpleXMLElement $resNode): array
    {
        $list = [];
        foreach (($resNode->RELAYPOSITIONS->RELAYPOSITION ?? []) as $p) {
            $list[] = $p;
        }
        if (!empty($list)) {
            return $list;
        }

        foreach (($relayNode->POSITIONS->POSITION ?? []) as $p) {
            $list[] = $p;
        }
        return $list;
    }

    /**
     * Relay-member athlete resolve:
     * - uses athlete_match mapping
     * - falls back to tmId
     * - creates athlete if missing
     *
     * @param  array<string,string|int>  $athleteMatch
     */
    private function resolveAthleteFromRelay(
        string $lenexAthleteId,
        ?SimpleXMLElement $nameNode,
        ParaClub $club,
        array $athleteMatch
    ): ?ParaAthlete {
        $choice = $lenexAthleteId !== '' ? ($athleteMatch[$lenexAthleteId] ?? null) : null;
        if (is_string($choice)) {
            $choice = trim($choice);
        }

        if ($choice && $choice !== 'auto') {
            if ($choice === 'new') {
                return $this->createRelayAthlete($lenexAthleteId, $nameNode, $club);
            }
            if (ctype_digit((string) $choice)) {
                $a = ParaAthlete::find((int) $choice);
                if ($a) {
                    $this->maybeAttachTmIdToAthlete($a, $lenexAthleteId);
                    if (empty($a->para_club_id)) {
                        $a->para_club_id = $club->id;
                    }
                    $a->save();
                    return $a->fresh();
                }
            }
        }

        if ($lenexAthleteId !== '' && ctype_digit($lenexAthleteId)) {
            $a = ParaAthlete::where('tmId', (int) $lenexAthleteId)->first();
            if ($a) {
                if (empty($a->para_club_id)) {
                    $a->para_club_id = $club->id;
                    $a->save();
                }
                return $a;
            }
        }

        return $this->createRelayAthlete($lenexAthleteId, $nameNode, $club);
    }

    private function createRelayAthlete(
        string $lenexAthleteId,
        ?SimpleXMLElement $nameNode,
        ParaClub $club
    ): ParaAthlete {
        $first = $nameNode ? trim((string) ($nameNode['firstname'] ?? $nameNode['givenname'] ?? '')) : '';
        $last = $nameNode ? trim((string) ($nameNode['lastname'] ?? $nameNode['familyname'] ?? '')) : '';
        $birthdate = $nameNode ? trim((string) ($nameNode['birthdate'] ?? '')) : '';
        $gender = $nameNode ? strtoupper(trim((string) ($nameNode['gender'] ?? ''))) : null;

        if ($first === '') {
            $first = 'Unknown';
        }
        if ($last === '') {
            $last = 'Unknown';
        }
        if (!in_array($gender, ['M', 'F', 'X'], true)) {
            $gender = null;
        }

        $a = new ParaAthlete();
        $a->firstName = $first;
        $a->lastName = $last;
        $a->gender = $gender;
        if ($birthdate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
            $a->birthdate = $birthdate;
        }

        $a->para_club_id = $club->id;

        // Handicap falls vorhanden
        if ($nameNode instanceof SimpleXMLElement) {
            $hc = $nameNode->HANDICAP ?? null;
            if ($hc instanceof SimpleXMLElement) {
                $free = trim((string) ($hc['free'] ?? ''));
                $breast = trim((string) ($hc['breast'] ?? ''));
                $medley = trim((string) ($hc['medley'] ?? ''));
                $exc = trim((string) ($hc['exception'] ?? ''));

                if ($free !== '' && ctype_digit($free)) {
                    $a->sportclass_s = 'S'.(int) $free;
                }
                if ($breast !== '' && ctype_digit($breast)) {
                    $a->sportclass_sb = 'SB'.(int) $breast;
                }
                if ($medley !== '' && ctype_digit($medley)) {
                    $a->sportclass_sm = 'SM'.(int) $medley;
                }
                if ($exc !== '') {
                    $a->sportclass_exception = $exc;
                }
            }
        }

        $this->maybeAttachTmIdToAthlete($a, $lenexAthleteId);

        $a->save();
        return $a->fresh();
    }

    private function maybeAttachTmIdToAthlete(ParaAthlete $a, string $lenexAthleteId): void
    {
        if ($lenexAthleteId === '' || !ctype_digit($lenexAthleteId)) {
            return;
        }
        if (!empty($a->tmId)) {
            return;
        }

        $tm = (int) $lenexAthleteId;

        $exists = ParaAthlete::where('tmId', $tm)->where('id', '!=', $a->id)->exists();
        if ($exists) {
            return;
        }

        $a->tmId = $tm;
    }

    private function findCumulativeAtDistance(array $splits, int $distance): ?int
    {
        foreach ($splits as $sp) {
            if ((int) $sp['distance'] === $distance) {
                return (int) $sp['ms'];
            }
        }
        return null;
    }

    private function insertLegSplits(int $memberId, array $relaySplits, int $leg, int $legDistance): void
    {
        $startAbs = ($leg - 1) * $legDistance;
        $endAbs = $leg * $legDistance;

        $startMs = $startAbs === 0 ? 0 : ($this->findCumulativeAtDistance($relaySplits, $startAbs) ?? 0);

        // alle splits innerhalb dieser leg (inkl. Ende)
        $legPoints = [];
        foreach ($relaySplits as $sp) {
            $d = (int) $sp['distance'];
            if ($d <= $startAbs) {
                continue;
            }
            if ($d > $endAbs) {
                continue;
            }

            $legPoints[] = [
                'abs' => $d,
                'ms' => (int) $sp['ms'],
            ];
        }
        if (empty($legPoints)) {
            return;
        }

        usort($legPoints, fn($a, $b) => $a['abs'] <=> $b['abs']);

        $prev = $startMs;
        foreach ($legPoints as $p) {
            $distInLeg = $p['abs'] - $startAbs;
            $cumInLeg = $p['ms'] - $startMs;
            $splitInLeg = $p['ms'] - $prev;

            $row = [
                'para_relay_member_id' => $memberId,
                'distance_in_leg' => $distInLeg,
                'cumulative_time_ms' => $cumInLeg,
                'split_time_ms' => $splitInLeg,
                'absolute_distance' => $p['abs'],
            ];
            $row = $this->filterColumns('para_relay_leg_splits', $row);

            DB::table('para_relay_leg_splits')->insert($row);
            $prev = $p['ms'];
        }
    }
}
