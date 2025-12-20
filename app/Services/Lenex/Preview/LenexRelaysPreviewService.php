<?php

namespace App\Services\Lenex\Preview;

use App\Models\ParaAthlete;
use App\Models\ParaClub;
use App\Models\ParaMeet;
use App\Services\Matching\AthleteMatchService;
use App\Services\Matching\ClubMatchService;
use App\Support\SwimTime;
use SimpleXMLElement;

class LenexRelaysPreviewService
{
    public function __construct(
        private readonly LenexPreviewSupport $support,
        private readonly AthleteMatchService $matcher,
        private ClubMatchService $clubMatcher,
    ) {
    }

    public function build(SimpleXMLElement $root, ParaMeet $meet): array
    {
        $meetNode = $root->MEETS->MEET[0] ?? null;
        /** @var SimpleXMLElement|null $meetNode */
        if (!$meetNode instanceof SimpleXMLElement) {
            return ['clubs' => []];
        }

        $meet->load('sessions.events.swimstyle', 'sessions.events.agegroups');

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

        // Prefetch clubs by tmId
        $lenexClubIds = [];
        foreach (($meetNode->CLUBS->CLUB ?? []) as $clubNode) {
            $cid = trim((string) ($clubNode['clubid'] ?? ''));
            if ($cid !== '' && ctype_digit($cid)) {
                $lenexClubIds[] = (int) $cid;
            }
        }
        $clubsByTmId = [];
        if (!empty($lenexClubIds)) {
            $clubsByTmId = ParaClub::query()
                ->whereIn('tmId', array_values(array_unique($lenexClubIds)))
                ->get()
                ->keyBy('tmId')
                ->all();
        }

        $clubs = [];

        foreach (($meetNode->CLUBS->CLUB ?? []) as $clubNode) {
            /** @var SimpleXMLElement $clubNode */
            $short = trim((string) ($clubNode['shortname'] ?? ''));
            $lenexClubId = trim((string) ($clubNode['clubid'] ?? ''));
            $clubName = trim((string) ($clubNode['name'] ?? ''));
            $nation = trim((string) ($clubNode['nation'] ?? ''));

            $clubPrimary = $this->clubMatcher->findByLenexClubId($lenexClubId);
            $clubCands = $this->clubMatcher->candidates($clubName, $short, 10);
            $clubAutoId = $clubPrimary?->id ?? $this->clubMatcher->autoSelectIdFromCandidates($clubCands, 90);

            $dbClub = null;
            if ($lenexClubId !== '' && ctype_digit($lenexClubId)) {
                $dbClub = $clubsByTmId[(int) $lenexClubId] ?? null;
            }
            if (!$dbClub && $clubName !== '') {
                $dbClub = ParaClub::query()
                    ->where('nameDe', $clubName)
                    ->orWhere('shortNameDe', $clubName)
                    ->orWhere('nameEn', $clubName)
                    ->orWhere('shortNameEn', $clubName)
                    ->first();
            }

            // Club-local athlete index (LENEX “belongs to club” check)
            $clubAthNodeById = [];
            foreach (($clubNode->ATHLETES->ATHLETE ?? []) as $athNode) {
                $aid = trim((string) ($athNode['athleteid'] ?? ''));
                if ($aid !== '') {
                    $clubAthNodeById[$aid] = $athNode;
                }
            }

            $relayRows = [];

            foreach (($clubNode->RELAYS->RELAY ?? []) as $relayNode) {
                /** @var SimpleXMLElement $relayNode */
                $relayNumber = (string) ($relayNode['number'] ?? '');
                $relayGender = (string) ($relayNode['gender'] ?? '');

                foreach (($relayNode->RESULTS->RESULT ?? []) as $resNode) {
                    /** @var SimpleXMLElement $resNode */
                    $ctx = $this->support->initResultContext($resNode, $eventByLenexId);

                    $resultId = $ctx['resultId'];
                    $lenexEventId = $ctx['lenexEventId'];
                    $swimtimeStr = $ctx['swimtimeStr'];

                    $invalidReasons = $ctx['invalidReasons']; // blockers
                    $warnings = [];

                    $event = $ctx['event'];

                    if (!$dbClub) {
                        $warnings[] = 'Verein nicht in para_clubs gefunden – wird beim Import automatisch angelegt.';
                    }

                    $eventLabel = SwimstyleLabel::relay($event);
                    $strokeCode = strtoupper((string) ($event?->swimstyle?->stroke_code ?? $event?->swimstyle?->stroke ?? 'FREE'));

                    $timeMs = $this->support->parseTimeToMs($swimtimeStr);

                    $agInfo = $resultId !== '' ? ($resultAgegroupByResultId[$resultId] ?? null) : null;
                    $relaySportClass = $this->resolveRelaySportClassCode($agInfo);

                    $positions = [];
                    $legIdx = 1;

                    foreach ($this->iterRelayPositions($relayNode, $resNode) as $posNode) {
                        /** @var SimpleXMLElement $posNode */
                        $aid = trim((string) ($posNode['athleteid'] ?? ''));
                        $leg = (int) ($posNode['number'] ?? $posNode['leg'] ?? $legIdx);

                        $inLenexClub = $aid !== '' && isset($clubAthNodeById[$aid]);

                        $globalAthNode = $aid !== '' ? ($globalAthNodeById[$aid] ?? null) : null;
                        $nameNode = $inLenexClub ? ($clubAthNodeById[$aid] ?? null) : $globalAthNode;

                        $first = $nameNode ? trim((string) ($nameNode['firstname'] ?? $nameNode['givenname'] ?? '')) : '';
                        $last = $nameNode ? trim((string) ($nameNode['lastname'] ?? $nameNode['familyname'] ?? '')) : '';
                        $birthdate = $nameNode ? trim((string) ($nameNode['birthdate'] ?? '')) : '';
                        $gender = $nameNode ? trim((string) ($nameNode['gender'] ?? '')) : '';

                        $primary = $this->matcher->findByLenexAthleteId($aid);

                        $cands = $this->matcher->candidates(
                            $first,
                            $last,
                            $birthdate ?: null,
                            $gender ?: null,
                            $dbClub?->id
                        );

                        $autoSelectedId = $primary?->id ?? $this->matcher->autoSelectIdFromCandidates($cands, 88);

                        if (!$inLenexClub) {
                            // This is the “red” rule you wanted: participants from other LENEX clubs
                            $invalidReasons[] = "LENEX#{$aid} ({$last}, {$first}) gehört im LENEX nicht zu diesem Verein";
                        }

                        if (!$autoSelectedId) {
                            $warnings[] = "Athlet {$last}, {$first} ({$aid}) nicht in para_athletes – wird beim Import automatisch angelegt (Status W).";
                        }

                        // DB club mismatch: highlight (also “red” if you want strict behaviour)
                        $dbAthlete = $primary ?: ($autoSelectedId ? ParaAthlete::find($autoSelectedId) : null);
                        $inDbClub = $this->support->athleteInClub($dbAthlete, $dbClub);
                        if ($dbAthlete && !$inDbClub && $dbClub) {
                            $invalidReasons[] = "Athlet {$dbAthlete->lastName}, {$dbAthlete->firstName} gehört im System nicht zu Verein {$clubName}";
                        }

                        $sportLabel = $this->support->athleteSportClassLabelForStroke($dbAthlete, $globalAthNode,
                            $strokeCode);
                        $sportNum = $this->support->athleteSportClassNumberForStroke($dbAthlete, $globalAthNode,
                            $strokeCode);

                        $positions[] = [
                            'leg' => $leg,
                            'lenex_athlete_id' => $aid,
                            'first_name' => $first,
                            'last_name' => $last,

                            'sport_class' => $sportLabel,
                            'sport_class_num' => $sportNum,

                            'match_candidates' => $cands,
                            'match_selected' => $autoSelectedId,
                        ];

                        $legIdx++;
                    }

                    // Relay sportclass rule check (S20/S34/S49 etc)
                    $check = $this->checkRelaySportClassRule($relaySportClass, $positions);
                    if (!$check['ok']) {
                        $invalidReasons[] = $check['message'];
                    }

                    $relayRows[] = [
                        'result_id' => $resultId ?: ($nation.'|'.$clubName.'|'.$relayNumber.'|'.$lenexEventId),
                        'lenex_resultid' => $resultId,
                        'lenex_eventid' => $lenexEventId,

                        'relay_event_label' => $eventLabel,
                        'relay_sportclass' => $relaySportClass,
                        'agegroup_name' => $agInfo['name'] ?? null,

                        'relay_number' => $relayNumber,
                        'relay_gender' => $relayGender,

                        'swimtime' => $swimtimeStr,
                        'time_ms' => $timeMs,
                        'swimtime_fmt' => $timeMs !== null ? SwimTime::format($timeMs) : ($swimtimeStr ?: '—'),

                        'positions' => $positions,

                        'invalid' => !empty($invalidReasons),
                        'invalid_reasons' => array_values(array_unique($invalidReasons)),
                        'warnings' => array_values(array_unique($warnings)),
                    ];
                }
            }

            if (!empty($relayRows)) {
                $clubs[] = [
                    'club_id' => $lenexClubId,
                    'club_name' => $clubName,
                    'nation' => $nation,
                    'relay_rows' => $relayRows,
                    'club_match_candidates' => $clubCands,
                    'club_match_selected' => $clubAutoId,  // kann null sein -> dann bleibt "auto"
                ];
            }
        }

        return ['clubs' => $clubs];
    }

    // -------------------- helpers --------------------

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

    private function buildResultAgegroupIndex(SimpleXMLElement $meetNode): array
    {
        $map = [];
        $bestOrder = [];

        foreach (($meetNode->SESSIONS->SESSION ?? []) as $sessionNode) {
            foreach (($sessionNode->EVENTS->EVENT ?? []) as $eventNode) {
                foreach (($eventNode->AGEGROUPS->AGEGROUP ?? []) as $agNode) {
                    $agName = (string) ($agNode['name'] ?? '');
                    $agHandicap = (string) ($agNode['handicap'] ?? '');
                    $agGender = (string) ($agNode['gender'] ?? '');

                    foreach (($agNode->RANKINGS->RANKING ?? []) as $rk) {
                        $rid = (string) ($rk['resultid'] ?? '');
                        if ($rid === '') {
                            continue;
                        }

                        $order = (int) ($rk['order'] ?? 999999);
                        if (!isset($bestOrder[$rid]) || $order < $bestOrder[$rid]) {
                            $bestOrder[$rid] = $order;
                            $map[$rid] = [
                                'name' => $agName,
                                'handicap' => $agHandicap,
                                'gender' => $agGender,
                            ];
                        }
                    }
                }
            }
        }

        return $map;
    }

    private function resolveRelaySportClassCode(?array $agInfo): ?string
    {
        if (!$agInfo) {
            return null;
        }

        $handicap = trim((string) ($agInfo['handicap'] ?? ''));
        if ($handicap !== '' && ctype_digit($handicap)) {
            $n = (int) $handicap;
            if (in_array($n, [14, 20, 21, 34, 49], true)) {
                return 'S'.$n;
            }
        }

        $name = mb_strtoupper((string) ($agInfo['name'] ?? ''));

        if (str_contains($name, '34') && str_contains($name, 'PKT')) {
            return 'S34';
        }
        if (str_contains($name, '20') && str_contains($name, 'PKT')) {
            return 'S20';
        }
        if (str_contains($name, 'T21')) {
            return 'S21';
        }
        if (str_contains($name, 'VI')) {
            return 'S49';
        }
        if (str_contains($name, 'MI')) {
            return 'S14';
        }

        return null;
    }

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

    private function checkRelaySportClassRule(?string $relaySportClass, array $positions): array
    {
        if (!$relaySportClass) {
            return ['ok' => true, 'message' => null];
        }

        $nums = [];
        foreach ($positions as $p) {
            $nums[] = $p['sport_class_num'] ?? null;
        }

        if (in_array(null, $nums, true)) {
            return [
                'ok' => false,
                'message' => "Sportklasse {$relaySportClass}: nicht alle Athleten haben eine Sportklasse."
            ];
        }

        $sum = array_sum($nums);

        $allBetween = function (int $min, int $max) use ($nums): bool {
            foreach ($nums as $c) {
                if ($c < $min || $c > $max) {
                    return false;
                }
            }
            return true;
        };

        $allIn = function (array $allowed) use ($nums): bool {
            $allowedMap = array_fill_keys($allowed, true);
            foreach ($nums as $v) {
                if (!isset($allowedMap[$v])) {
                    return false;
                }
            }
            return true;
        };

        return match ($relaySportClass) {
            'S14' => $allIn([14, 21]) ? ['ok' => true, 'message' => null] : [
                'ok' => false, 'message' => 'S14: erlaubt nur 14 oder 21.'
            ],
            'S21' => $allIn([21]) ? ['ok' => true, 'message' => null] : [
                'ok' => false, 'message' => 'S21: erlaubt nur 21.'
            ],
            'S20' => ($allBetween(1, 10) && $sum <= 20) ? ['ok' => true, 'message' => null] : [
                'ok' => false, 'message' => "S20: Klassen 1–10 und Summe ≤ 20 (aktuell {$sum})."
            ],
            'S34' => ($allBetween(1, 10) && $sum <= 34) ? ['ok' => true, 'message' => null] : [
                'ok' => false, 'message' => "S34: Klassen 1–10 und Summe ≤ 34 (aktuell {$sum})."
            ],
            'S49' => ($allBetween(11, 13) && $sum <= 49) ? ['ok' => true, 'message' => null] : [
                'ok' => false, 'message' => "S49: Klassen 11–13 und Summe ≤ 49 (aktuell {$sum})."
            ],
            default => ['ok' => true, 'message' => null],
        };
    }
}
