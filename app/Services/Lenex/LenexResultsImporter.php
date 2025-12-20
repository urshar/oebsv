<?php

namespace App\Services\Lenex;

use App\Models\ParaAthlete;
use App\Models\ParaClub;
use App\Models\ParaEntry;
use App\Models\ParaMeet;
use App\Models\ParaResult;
use App\Models\ParaSplit;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class LenexResultsImporter
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
    public function importSelected(
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
            throw new RuntimeException('Keine MEET-Definition im LENEX gefunden (Results).');
        }

        $meet->load('sessions.events.agegroups', 'sessions.events.swimstyle');

        // eventid -> ParaEvent
        $eventByLenexId = [];
        foreach ($meet->sessions as $session) {
            foreach ($session->events as $event) {
                if (!empty($event->lenex_eventid)) {
                    $eventByLenexId[(string) $event->lenex_eventid] = $event;
                }
            }
        }

        // resultid -> lenex_agegroupid (best order)
        $resultAgegroupByResultId = $this->buildResultAgegroupIndex($meetNode);
        $heatLaneByResultId = $this->buildHeatLaneIndex($meetNode);
        $heatNoByHeatId = $this->buildHeatNoByHeatIdIndex($meetNode);

        DB::transaction(function () use (
            $meet,
            $meetNode,
            $selected,
            $eventByLenexId,
            $resultAgegroupByResultId,
            $athleteMatch,
            $heatLaneByResultId,
            $heatNoByHeatId,
            $clubMatch
        ) {
            foreach (($meetNode->CLUBS->CLUB ?? []) as $clubNode) {
                /** @var SimpleXMLElement $clubNode */
                $lenexClubId = trim((string) ($clubNode['clubid'] ?? ''));
                $clubName = trim((string) ($clubNode['name'] ?? ''));

                $dbClub = $this->resolveClub($clubNode, $clubMatch);

                foreach (($clubNode->ATHLETES->ATHLETE ?? []) as $athNode) {
                    /** @var SimpleXMLElement $athNode */
                    $lenexAthleteId = trim((string) ($athNode['athleteid'] ?? ''));

                    $first = trim((string) ($athNode['firstname'] ?? $athNode['givenname'] ?? ''));
                    $last = trim((string) ($athNode['lastname'] ?? $athNode['familyname'] ?? ''));
                    $birthdate = trim((string) ($athNode['birthdate'] ?? ''));
                    $gender = strtoupper(trim((string) ($athNode['gender'] ?? '')));

                    $dbAthlete = $this->resolveAthlete($athNode, $dbClub, $athleteMatch);

                    foreach (($athNode->RESULTS->RESULT ?? []) as $resNode) {
                        /** @var SimpleXMLElement $resNode */
                        $lenexEventId = trim((string) ($resNode['eventid'] ?? ''));
                        $lenexResultId = trim((string) ($resNode['resultid'] ?? ''));

                        $key = $lenexResultId !== ''
                            ? $lenexResultId
                            : ($lenexAthleteId.'|'.$lenexEventId.'|'.$clubName);

                        if (!isset($selected[$key])) {
                            continue;
                        }

                        $event = $lenexEventId !== '' ? ($eventByLenexId[$lenexEventId] ?? null) : null;
                        if (!$event) {
                            // Preview markiert das als invalid – hier skip.
                            continue;
                        }

                        // Agegroup optional
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

                        // Entry: wird immer angelegt / upserted (Unique: para_event_id + para_athlete_id)
                        $entry = ParaEntry::updateOrCreate(
                            [
                                'para_event_id' => $event->id,
                                'para_athlete_id' => $dbAthlete->id,
                            ],
                            [
                                'para_meet_id' => $meet->id,
                                'para_session_id' => $event->para_session_id,
                                'para_event_agegroup_id' => $paraEventAgegroupId,
                                'para_club_id' => $dbClub?->id,

                                'lenex_athleteid' => $lenexAthleteId !== '' ? $lenexAthleteId : null,
                                'lenex_eventid' => $lenexEventId !== '' ? $lenexEventId : null,

                                // Results-LENEX hat meistens keine entrytime -> null lassen
                                'entry_time' => null,
                                'entry_time_ms' => null,
                            ]
                        );

                        // Result
                        $swimtimeStr = trim((string) ($resNode['swimtime'] ?? ''));
                        $timeMs = $this->lenex->parseTimeToMs($swimtimeStr);

                        $reactionStr = trim((string) ($resNode['reactiontime'] ?? ''));
                        $reactionMs = $this->lenex->parseTimeToMs($reactionStr);

                        // rank/points: zuerst direkt am RESULT, sonst aus RANKINGS-Index (resultid)
                        $rank = $this->firstIntAttr($resNode, ['rank', 'place', 'position', 'pos']);
                        if ($rank === null && $lenexResultId !== '') {
                            $rank = $resultAgegroupByResultId[$lenexResultId]['rank'] ?? null;
                        }

                        $points = $this->firstIntAttr($resNode, ['points', 'point', 'score']);
                        if ($points === null && $lenexResultId !== '') {
                            $points = $resultAgegroupByResultId[$lenexResultId]['points'] ?? null;
                        }

// lane: direkt am RESULT oder aus Heat/Lane-Index
                        $lane = $this->firstIntAttr($resNode, ['lane', 'laneno']);
                        if ($lane === null && $lenexResultId !== '') {
                            $lane = $heatLaneByResultId[$lenexResultId]['lane'] ?? null;
                        }

// heat: selten am RESULT. Häufig über heatid oder Parent HEAT.
                        $heat = $this->firstIntAttr($resNode, ['heat', 'heatno', 'heatnumber']);
                        if ($heat === null) {
                            $heatId = trim((string) ($resNode['heatid'] ?? ''));
                            if ($heatId !== '') {
                                $heat = $heatNoByHeatId[$heatId] ?? null;
                            }
                        }
                        if ($heat === null && $lenexResultId !== '') {
                            $heat = $heatLaneByResultId[$lenexResultId]['heat'] ?? null;
                        }

                        $status = isset($resNode['status']) ? (string) $resNode['status'] : null;

                        $round = null;
                        if (isset($resNode['round']) && (string) $resNode['round'] !== '') {
                            $round = (string) $resNode['round'];
                        } elseif (!empty($event->round)) {
                            $round = (string) $event->round;
                        }

                        $result = ParaResult::updateOrCreate(
                            ['para_entry_id' => $entry->id],
                            array_filter([
                                'para_meet_id' => $meet->id,
                                'time_ms' => $timeMs,
                                'reaction_time_ms' => $reactionMs,
                                'rank' => $rank,
                                'heat' => $heat,
                                'lane' => $lane,
                                'round' => $round,
                                'status' => $status,
                                'points' => $points,
                            ], fn($v) => $v !== null)
                        );

                        // Splits neu schreiben
                        ParaSplit::where('para_result_id', $result->id)->delete();

                        foreach (($resNode->SPLITS->SPLIT ?? []) as $splitNode) {
                            /** @var SimpleXMLElement $splitNode */
                            $dist = isset($splitNode['distance']) ? (int) $splitNode['distance'] : null;
                            $tStr = trim((string) ($splitNode['swimtime'] ?? ''));
                            $tMs = $this->lenex->parseTimeToMs($tStr);

                            if (!$dist || $tMs === null) {
                                continue;
                            }

                            ParaSplit::create([
                                'para_result_id' => $result->id,
                                'distance' => $dist,
                                'time_ms' => $tMs,
                            ]);
                        }
                    }
                }
            }
        });
    }

    // ---------- CLUB / ATHLETE RESOLVE ----------

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
                            $rank = $this->firstIntAttr($rk, ['place', 'rank', 'position', 'pos', 'order']);
                            $points = $this->firstIntAttr($rk, ['points', 'point', 'score']);

                            $map[$rid] = [
                                'agegroupid' => $agegroupid,
                                'name' => $name,
                                'rank' => $rank,
                                'points' => $points,
                            ];
                        }
                    }
                }
            }
        }

        return $map;
    }

    private function firstIntAttr(SimpleXMLElement $node, array $attrs): ?int
    {
        foreach ($attrs as $a) {
            if (isset($node[$a]) && (string) $node[$a] !== '') {
                return (int) $node[$a];
            }
        }
        return null;
    }

    /**
     * resultid -> ['heat' => int, 'lane' => int]
     */
    private function buildHeatLaneIndex(SimpleXMLElement $meetNode): array
    {
        $map = [];

        foreach (($meetNode->SESSIONS->SESSION ?? []) as $sessionNode) {
            foreach (($sessionNode->EVENTS->EVENT ?? []) as $eventNode) {

                foreach (($eventNode->HEATS->HEAT ?? []) as $heatNode) {
                    $heatNo = $this->firstIntAttr($heatNode, ['number', 'no', 'heat']);

                    // Variante A: HEAT->RESULTS->RESULT
                    foreach (($heatNode->RESULTS->RESULT ?? []) as $resNode) {
                        $rid = trim((string) ($resNode['resultid'] ?? ''));
                        if ($rid === '') {
                            continue;
                        }

                        $lane = $this->firstIntAttr($resNode, ['lane', 'laneno']);

                        if (!isset($map[$rid])) {
                            $map[$rid] = [];
                        }
                        if ($heatNo !== null) {
                            $map[$rid]['heat'] = $heatNo;
                        }
                        if ($lane !== null) {
                            $map[$rid]['lane'] = $lane;
                        }
                    }

                    // Variante B: HEAT->LANES->LANE->RESULT
                    foreach (($heatNode->LANES->LANE ?? []) as $laneNode) {
                        $laneNo = $this->firstIntAttr($laneNode, ['number', 'no', 'lane']);

                        foreach (($laneNode->RESULT ?? []) as $resNode) {
                            $rid = trim((string) ($resNode['resultid'] ?? ''));
                            if ($rid === '') {
                                continue;
                            }

                            if (!isset($map[$rid])) {
                                $map[$rid] = [];
                            }
                            if ($heatNo !== null) {
                                $map[$rid]['heat'] = $heatNo;
                            }
                            if ($laneNo !== null) {
                                $map[$rid]['lane'] = $laneNo;
                            }
                        }
                    }
                }
            }
        }

        return $map;
    }

    private function buildHeatNoByHeatIdIndex(SimpleXMLElement $meetNode): array
    {
        $map = [];

        foreach (($meetNode->SESSIONS->SESSION ?? []) as $sessionNode) {
            foreach (($sessionNode->EVENTS->EVENT ?? []) as $eventNode) {

                foreach (($eventNode->HEATS->HEAT ?? []) as $heatNode) {
                    $heatNo = $this->firstIntAttr($heatNode, ['number', 'no', 'heat']);
                    $heatId = trim((string) ($heatNode['heatid'] ?? $heatNode['id'] ?? ''));

                    if ($heatNo !== null && $heatId !== '') {
                        $map[$heatId] = $heatNo;
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
        $lenexClubId = trim((string) ($clubNode['clubid'] ?? ''));
        $name = trim((string) ($clubNode['name'] ?? ''));
        $short = trim((string) ($clubNode['shortname'] ?? ''));

        // 1) User selection
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
                    return $club;
                }
            }
        }

        // 2) Auto: tmId match
        if ($lenexClubId !== '' && ctype_digit($lenexClubId)) {
            $club = ParaClub::where('tmId', (int) $lenexClubId)->first();
            if ($club) {
                return $club;
            }
        }

        // 3) Auto: exact name match
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
                return $club;
            }
        }

        // 4) Create new
        return $this->createOrUpdateClubFromLenex($lenexClubId, $name, $short);
    }

    private function createOrUpdateClubFromLenex(string $lenexClubId, string $name, string $short): ParaClub
    {
        $nameDe = $name !== '' ? $name : ('Unbekannter Verein'.($lenexClubId !== '' ? " ({$lenexClubId})" : ''));

        // wenn derselbe Name schon existiert, nehmen wir den (verhindert unique(nation_id,nameDe) Probleme)
        $club = ParaClub::where('nameDe', $nameDe)->first();
        if (!$club) {
            $club = new ParaClub();
            $club->nameDe = $nameDe;
        }

        if ($short !== '' && empty($club->shortNameDe)) {
            $club->shortNameDe = $short;
        }

        // best effort: tmId setzen
        $this->maybeAttachTmIdToClub($club, $lenexClubId);

        // optional: altName füllen
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

        // tmId darf nicht schon bei einem anderen Club hängen
        $exists = ParaClub::where('tmId', $tm)->where('id', '!=', $club->id)->exists();
        if ($exists) {
            return;
        }

        $club->tmId = $tm;
    }

    // ---------- Helpers ----------

    /**
     * @param  array<string,string|int>  $athleteMatch
     */
    private function resolveAthlete(SimpleXMLElement $athNode, ?ParaClub $club, array $athleteMatch): ParaAthlete
    {
        $lenexAthleteId = trim((string) ($athNode['athleteid'] ?? ''));

        $first = trim((string) ($athNode['firstname'] ?? $athNode['givenname'] ?? ''));
        $last = trim((string) ($athNode['lastname'] ?? $athNode['familyname'] ?? ''));
        $birthdate = trim((string) ($athNode['birthdate'] ?? ''));
        $gender = strtoupper(trim((string) ($athNode['gender'] ?? '')));

        // 1) User selection
        $choice = $lenexAthleteId !== '' ? ($athleteMatch[$lenexAthleteId] ?? null) : null;
        if (is_string($choice)) {
            $choice = trim($choice);
        }

        if ($choice && $choice !== 'auto') {
            if ($choice === 'new') {
                return $this->createAthleteFromLenex($athNode, $club);
            }
            if (ctype_digit((string) $choice)) {
                $a = ParaAthlete::find((int) $choice);
                if ($a) {
                    $this->maybeAttachTmIdToAthlete($a, $lenexAthleteId);
                    if ($club && empty($a->para_club_id)) {
                        $a->para_club_id = $club->id;
                    }
                    $a->save();
                    return $a->fresh();
                }
            }
        }

        // 2) Auto: tmId match
        if ($lenexAthleteId !== '' && ctype_digit($lenexAthleteId)) {
            $a = ParaAthlete::where('tmId', (int) $lenexAthleteId)->first();
            if ($a) {
                if ($club && empty($a->para_club_id)) {
                    $a->para_club_id = $club->id;
                    $a->save();
                }
                return $a;
            }
        }

        // 3) Auto: exact name + birthdate
        if ($first !== '' && $last !== '') {
            $q = ParaAthlete::query()
                ->whereRaw('LOWER(firstName) = ?', [mb_strtolower($first)])
                ->whereRaw('LOWER(lastName) = ?', [mb_strtolower($last)]);

            if ($birthdate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
                $q->where('birthdate', $birthdate);
            }

            $a = $q->first();
            if ($a) {
                $this->maybeAttachTmIdToAthlete($a, $lenexAthleteId);
                if ($club && empty($a->para_club_id)) {
                    $a->para_club_id = $club->id;
                }
                $a->save();
                return $a->fresh();
            }
        }

        // 4) Create new
        return $this->createAthleteFromLenex($athNode, $club);
    }

    private function createAthleteFromLenex(SimpleXMLElement $athNode, ?ParaClub $club): ParaAthlete
    {
        $lenexAthleteId = trim((string) ($athNode['athleteid'] ?? ''));

        $first = trim((string) ($athNode['firstname'] ?? $athNode['givenname'] ?? ''));
        $last = trim((string) ($athNode['lastname'] ?? $athNode['familyname'] ?? ''));

        if ($first === '') {
            $first = 'Unknown';
        }
        if ($last === '') {
            $last = 'Unknown';
        }

        $birthdate = trim((string) ($athNode['birthdate'] ?? ''));
        $gender = strtoupper(trim((string) ($athNode['gender'] ?? '')));
        if (!in_array($gender, ['M', 'F', 'X'], true)) {
            $gender = null;
        }

        $a = new ParaAthlete();
        $a->firstName = $first;
        $a->lastName = $last;
        $a->gender = $gender;

        if ($birthdate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
            $a->birthdate = $birthdate;
        }

        if ($club) {
            $a->para_club_id = $club->id;
        }

        // Handicap -> sportclass fields
        $this->fillSportclassesFromLenex($a, $athNode);

        // tmId (LENEX athleteid)
        $this->maybeAttachTmIdToAthlete($a, $lenexAthleteId);

        $a->save();
        return $a->fresh();
    }

    private function fillSportclassesFromLenex(ParaAthlete $a, SimpleXMLElement $athNode): void
    {
        $hc = $athNode->HANDICAP ?? null;
        if (!$hc instanceof SimpleXMLElement) {
            return;
        }

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

}
