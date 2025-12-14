<?php

namespace App\Services\Lenex\Preview;

use App\Models\ParaAthlete;
use App\Models\ParaClub;
use App\Models\ParaEntry;
use App\Models\ParaMeet;
use App\Support\SwimTime;
use Illuminate\Support\Facades\Schema;
use SimpleXMLElement;
use Throwable;

class LenexRelaysPreviewService
{
    public function __construct(
        private readonly LenexPreviewSupport $support
    ) {
    }

    /**
     * Returns:
     * [
     *   'clubs' => [
     *      ['nation'=>..., 'club_name'=>..., 'relay_rows'=> [...]],
     *   ]
     * ]
     */
    public function build(SimpleXMLElement $root, ParaMeet $meet): array
    {
        /** @var SimpleXMLElement|null $meetNode */
        $meetNode = $root->MEETS->MEET[0] ?? null;
        if (!$meetNode instanceof SimpleXMLElement) {
            return ['clubs' => []];
        }

        // Globaler LENEX Athlete Index: athleteid => ATHLETE (für Name + HANDICAP)
        $globalAthNodeById = $this->buildGlobalAthleteIndex($meetNode);

        // resultid -> agegroup info (für Relay-Sportklasse wie S14/S34/S49)
        $resultAgegroupByResultId = $this->buildResultAgegroupIndex($meetNode);

        // DB: Events + Swimstyle
        $meet->load('sessions.events.swimstyle');

        $eventByLenexId = [];
        foreach ($meet->sessions as $session) {
            foreach ($session->events as $event) {
                if (!empty($event->lenex_eventid)) {
                    $eventByLenexId[(string) $event->lenex_eventid] = $event;
                }
            }
        }

        // Sammle alle athleteids aus Relay-Positionen -> schneller DB lookup via ParaEntry
        $allPosIds = [];
        foreach (($meetNode->CLUBS->CLUB ?? []) as $clubNode) {
            foreach (($clubNode->RELAYS->RELAY ?? []) as $relayNode) {
                foreach (($relayNode->RESULTS->RESULT ?? []) as $resNode) {
                    foreach ($this->iterRelayPositions($relayNode, $resNode) as $posNode) {
                        $aid = (string) ($posNode['athleteid'] ?? '');
                        if ($aid !== '') {
                            $allPosIds[] = $aid;
                        }
                    }
                }
            }
        }
        $allPosIds = array_values(array_unique($allPosIds));

        $entriesByLenexAthleteId = ParaEntry::query()
            ->where('para_meet_id', $meet->id)
            ->when(!empty($allPosIds), fn($q) => $q->whereIn('lenex_athleteid', $allPosIds))
            ->with('athlete.club')
            ->get()
            ->keyBy('lenex_athleteid');

        $clubs = [];

        foreach (($meetNode->CLUBS->CLUB ?? []) as $clubNode) {
            /** @var SimpleXMLElement $clubNode */
            $lenexClubId = (string) ($clubNode['clubid'] ?? '');
            $clubName = trim((string) ($clubNode['name'] ?? ''));
            $nation = trim((string) ($clubNode['nation'] ?? ''));

            $dbClub = $this->findClub($lenexClubId, $clubName);

            // Club-interner Athleten Index (für "gehört im LENEX nicht zu diesem Verein")
            $clubAthNodeById = [];
            foreach (($clubNode->ATHLETES->ATHLETE ?? []) as $athNode) {
                $aid = (string) ($athNode['athleteid'] ?? '');
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

                    // ✅ kein Duplicate mehr: Result Context über Support (inkl. missing event/club)
                    $ctx = $this->support->initResultContext($resNode, $eventByLenexId, $dbClub);

                    $resultId = $ctx['resultId'];
                    $lenexEventId = $ctx['lenexEventId'];
                    $swimtimeStr = $ctx['swimtimeStr'];
                    $invalidReasons = $ctx['invalidReasons'];
                    $event = $ctx['event'];

                    $eventLabel = SwimstyleLabel::relay($event);

                    // Relay Sportklasse aus AGEGROUP/RANKING context (S14/S20/S21/S34/S49)
                    $agInfo = $resultId !== '' ? ($resultAgegroupByResultId[$resultId] ?? null) : null;
                    $relaySportClass = $this->resolveRelaySportClassCode($agInfo);

                    $timeMs = $this->support->parseTimeToMs($swimtimeStr);

                    // Stroke code für LENEX Handicap Auswahl (FREE/BREAST/MEDLEY)
                    $strokeCode = strtoupper((string) ($event?->swimstyle?->stroke ?? 'FREE'));

                    $positions = [];
                    $legIdx = 1;

                    foreach ($this->iterRelayPositions($relayNode, $resNode) as $posNode) {
                        $aid = (string) ($posNode['athleteid'] ?? '');
                        $leg = (int) ($posNode['number'] ?? $posNode['leg'] ?? $legIdx);

                        $dbAthlete = null;

                        $inLenexClub = $aid !== '' && isset($clubAthNodeById[$aid]);

                        $globalAthNode = $globalAthNodeById[$aid] ?? null;
                        $nameNode = $inLenexClub ? ($clubAthNodeById[$aid] ?? null) : $globalAthNode;

                        $first = $nameNode ? trim((string) ($nameNode['firstname'] ?? $nameNode['givenname'] ?? '')) : '';
                        $last = $nameNode ? trim((string) ($nameNode['lastname'] ?? $nameNode['familyname'] ?? '')) : '';
                        $birthdate = $nameNode ? trim((string) ($nameNode['birthdate'] ?? '')) : '';

                        // Prefer ParaEntry mapping
                        $mappedEntry = $aid !== '' ? ($entriesByLenexAthleteId[$aid] ?? null) : null;
                        $dbAthlete = $mappedEntry?->athlete;

                        // Fallback by name (+ birthdate)
                        if (!$dbAthlete && $first !== '' && $last !== '') {
                            $q = ParaAthlete::query()
                                ->with('club')
                                ->whereRaw('LOWER(firstName) = ?', [mb_strtolower($first)])
                                ->whereRaw('LOWER(lastName) = ?', [mb_strtolower($last)]);
                            if ($birthdate !== '') {
                                $q->whereDate('birthdate', $birthdate);
                            }
                            $dbAthlete = $q->first();
                        }

                        // Namen auffüllen aus DB wenn LENEX leer
                        if (($first === '' || $last === '') && $dbAthlete) {
                            $first = (string) $dbAthlete->firstName;
                            $last = (string) $dbAthlete->lastName;
                        }

                        $existsInDb = (bool) $dbAthlete;
                        $inDbClub = $this->support->athleteInClub($dbAthlete, $dbClub);

                        // LENEX-Club mismatch als eigene Meldung (zusätzlich zu DB-Club mismatch)
                        if (!$inLenexClub) {
                            $invalidReasons[] = "LENEX#{$aid} ({$last}, {$first}) gehört im LENEX nicht zu diesem Verein";
                        }

                        // DB-Reasons (nicht vorhanden / anderer Verein)
                        $this->support->addAthleteDbReasons(
                            $invalidReasons,
                            $dbAthlete,
                            $inDbClub,
                            $first,
                            $last,
                            $aid,
                            $clubName
                        );

                        // Sportklasse pro Athlet (DB-first; fallback LENEX HANDICAP)
                        $sportClass = $this->getSportClassNumber($dbAthlete);
                        if ($sportClass === null) {
                            $sportClass = $this->lenexAthleteSportClass($globalAthNode, $strokeCode);
                        }

                        $positions[] = [
                            'leg' => $leg,
                            'lenex_athlete_id' => $aid,
                            'first_name' => $first,
                            'last_name' => $last,
                            'db_athlete_id' => $dbAthlete?->id,

                            'exists_in_db' => $existsInDb,
                            'in_lenex_club' => $inLenexClub,
                            'in_db_club' => $inDbClub,

                            'sport_class' => $sportClass,
                        ];

                        $legIdx++;
                    }

                    // Relay Sportklassen-Regeln prüfen (Summe / erlaubte Klassen)
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
                    ];
                }
            }

            if (!empty($relayRows)) {
                $clubs[] = [
                    'club_id' => $lenexClubId,
                    'club_name' => $clubName,
                    'nation' => $nation,
                    'relay_rows' => $relayRows,
                ];
            }
        }

        return ['clubs' => $clubs];
    }

    // ------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------

    private function buildGlobalAthleteIndex(SimpleXMLElement $meetNode): array
    {
        $idx = [];

        foreach (($meetNode->CLUBS->CLUB ?? []) as $cNode) {
            foreach (($cNode->ATHLETES->ATHLETE ?? []) as $aNode) {
                $id = (string) ($aNode['athleteid'] ?? '');
                if ($id !== '') {
                    $idx[$id] = $aNode;
                }
            }
        }

        return $idx;
    }

    /**
     * resultid -> ['name'=>..., 'handicap'=>..., 'gender'=>...]
     * Bestes Ranking (kleinste order) gewinnt.
     */
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

    /**
     * Unterstützt beide Varianten:
     *  - RESULT/RELAYPOSITIONS/RELAYPOSITION
     *  - RELAY/POSITIONS/POSITION
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

    private function findClub(string $lenexClubId, string $clubName): ?ParaClub
    {
        // Wenn du ParaClub::findByLenexOrName() eingebaut hast, nutze das:
        if (method_exists(ParaClub::class, 'findByLenexOrName')) {
            /** @phpstan-ignore-next-line */
            return ParaClub::findByLenexOrName($lenexClubId, $clubName);
        }

        // Fallback (falls nicht vorhanden)
        $q = ParaClub::query();

        if ($lenexClubId !== '' && Schema::hasColumn('para_clubs', 'lenex_clubid')) {
            $club = (clone $q)->where('lenex_clubid', $lenexClubId)->first();
            if ($club) {
                return $club;
            }
        }

        if ($clubName === '') {
            return null;
        }

        return $q->where(function ($qq) use ($clubName) {
            $qq->where('nameDe', $clubName)
                ->orWhere('shortNameDe', $clubName)
                ->orWhere('nameEn', $clubName)
                ->orWhere('shortNameEn', $clubName);
        })->first();
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

    /**
     * DB-first: versucht eine Zahl aus Athlete oder (falls vorhanden) aus Klassifikations-Historie zu lesen.
     */
    private function getSportClassNumber(?ParaAthlete $athlete): ?int
    {
        if (!$athlete) {
            return null;
        }

        foreach (['sport_class', 'sportclass', 'sportClass', 'class'] as $attr) {
            if (isset($athlete->{$attr}) && $athlete->{$attr} !== null && $athlete->{$attr} !== '') {
                $v = (string) $athlete->{$attr};
                if (ctype_digit($v)) {
                    return (int) $v;
                }
            }
        }

        // optional: classifications relation (wenn vorhanden)
        if (method_exists($athlete, 'classifications')) {
            try {
                $cls = $athlete->classifications()->orderByDesc('valid_from')->first();
                if ($cls) {
                    foreach (['sport_class', 'sportclass', 'sportClass', 'class'] as $attr) {
                        if (isset($cls->{$attr}) && $cls->{$attr} !== null && $cls->{$attr} !== '') {
                            $v = (string) $cls->{$attr};
                            if (ctype_digit($v)) {
                                return (int) $v;
                            }
                        }
                    }
                }
            } catch (Throwable) {
                // ignore
            }
        }

        return null;
    }

    /**
     * LENEX fallback: liest aus ATHLETE/HANDICAP free|breast|medley
     */
    private function lenexAthleteSportClass(?SimpleXMLElement $athNode, string $strokeCode): ?int
    {
        if (!$athNode) {
            return null;
        }

        $hc = $athNode->HANDICAP ?? null;
        if (!$hc instanceof SimpleXMLElement) {
            return null;
        }

        $strokeCode = strtoupper(trim($strokeCode));

        $attr = match ($strokeCode) {
            'BREAST', 'BREASTSTROKE' => 'breast',
            'MEDLEY', 'IM' => 'medley',
            default => 'free', // FREE/BACK/FLY -> S-Klasse
        };

        $val = trim((string) ($hc[$attr] ?? ''));
        return ($val !== '' && ctype_digit($val)) ? (int) $val : null;
    }

    private function checkRelaySportClassRule(?string $relaySportClass, array $positions): array
    {
        if (!$relaySportClass) {
            return ['ok' => true, 'message' => null];
        }

        $classes = array_map(
            fn($p) => $p['sport_class'] ?? null,
            $positions
        );

        if (in_array(null, $classes, true)) {
            return [
                'ok' => false,
                'message' => "Sportklasse {$relaySportClass}: nicht alle Athleten haben eine Sportklasse (DB oder LENEX HANDICAP)."
            ];
        }

        $sum = array_sum($classes);

        $allBetween = function (int $min, int $max) use ($classes): bool {
            foreach ($classes as $c) {
                if ($c < $min || $c > $max) {
                    return false;
                }
            }
            return true;
        };

        return match ($relaySportClass) {
            'S14' => $this->allIn($classes, [14, 21])
                ? ['ok' => true, 'message' => null]
                : ['ok' => false, 'message' => 'Sportklasse S14: erlaubt sind nur Klassen 14 oder 21.'],

            'S21' => $this->allIn($classes, [21])
                ? ['ok' => true, 'message' => null]
                : ['ok' => false, 'message' => 'Sportklasse S21: erlaubt sind nur Athleten mit Klasse 21.'],

            'S20' => ($allBetween(1, 10) && $sum <= 20)
                ? ['ok' => true, 'message' => null]
                : ['ok' => false, 'message' => "Sportklasse S20: Klassen 1–10 und Summe ≤ 20 (aktuell {$sum})."],

            'S34' => ($allBetween(1, 10) && $sum <= 34)
                ? ['ok' => true, 'message' => null]
                : ['ok' => false, 'message' => "Sportklasse S34: Klassen 1–10 und Summe ≤ 34 (aktuell {$sum})."],

            'S49' => ($allBetween(11, 13) && $sum <= 49)
                ? ['ok' => true, 'message' => null]
                : ['ok' => false, 'message' => "Sportklasse S49: Klassen 11–13 und Summe ≤ 49 (aktuell {$sum})."],

            default => ['ok' => true, 'message' => null],
        };
    }

    private function allIn(array $values, array $allowed): bool
    {
        $allowedMap = array_fill_keys($allowed, true);
        foreach ($values as $v) {
            if (!isset($allowedMap[$v])) {
                return false;
            }
        }
        return true;
    }
}
