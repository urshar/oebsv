<?php

namespace App\Services\Lenex\Preview;

use App\Models\ParaClub;
use App\Models\ParaEntry;
use App\Models\ParaMeet;
use App\Services\Lenex\LenexEntryIndex;
use App\Services\Matching\AthleteMatchService;
use App\Services\Matching\ClubMatchService;
use App\Support\SwimTime;
use SimpleXMLElement;

readonly class LenexResultsPreviewService
{
    public function __construct(
        private LenexPreviewSupport $support,
        private AthleteMatchService $matcher,
        private ClubMatchService $clubMatcher,
    ) {
    }

    public function build(SimpleXMLElement $root, ParaMeet $meet): array
    {
        /** @var SimpleXMLElement|null $meetNode */
        $meetNode = $root->MEETS->MEET[0] ?? null;
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

        // Existing entries (if any)
        $entries = ParaEntry::query()
            ->where('para_meet_id', $meet->id)
            ->with(['athlete.club', 'club'])
            ->get();
        $entryByAthEvent = LenexEntryIndex::byAthleteEvent($entries);

        // Prefetch clubs by tmId for quick primary matches
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
            $clubName = trim((string) ($clubNode['name'] ?? ''));
            $nation = trim((string) ($clubNode['nation'] ?? ''));
            $lenexClubId = trim((string) ($clubNode['clubid'] ?? ''));

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

            $rows = [];

            foreach (($clubNode->ATHLETES->ATHLETE ?? []) as $athNode) {
                /** @var SimpleXMLElement $athNode */
                $lenexAthleteId = trim((string) ($athNode['athleteid'] ?? ''));
                $first = trim((string) ($athNode['firstname'] ?? $athNode['givenname'] ?? ''));
                $last = trim((string) ($athNode['lastname'] ?? $athNode['familyname'] ?? ''));
                $birthdate = trim((string) ($athNode['birthdate'] ?? ''));
                $gender = trim((string) ($athNode['gender'] ?? ''));

                // Candidates for matching (even if athlete doesn't exist yet)
                $cands = $this->matcher->candidates(
                    $first,
                    $last,
                    $birthdate ?: null,
                    $gender ?: null,
                    $dbClub?->id
                );

                $primaryByTmId = $this->matcher->findByLenexAthleteId($lenexAthleteId);
                $autoSelectedId = $primaryByTmId?->id ?? $this->matcher->autoSelectIdFromCandidates($cands, 88);

                foreach (($athNode->RESULTS->RESULT ?? []) as $resNode) {
                    /** @var SimpleXMLElement $resNode */

                    $ctx = $this->support->initResultContext($resNode, $eventByLenexId);

                    $resultId = $ctx['resultId'];
                    $lenexEventId = $ctx['lenexEventId'];
                    $swimtimeStr = $ctx['swimtimeStr'];

                    $invalidReasons = $ctx['invalidReasons']; // ONLY blockers
                    $warnings = []; // non-blocking

                    $event = $ctx['event'];

                    // Entry: optional
                    $entry = null;
                    if ($lenexAthleteId !== '' && $lenexEventId !== '') {
                        $entry = $entryByAthEvent[$lenexAthleteId.'|'.$lenexEventId] ?? null;
                    }
                    if (!$entry) {
                        $warnings[] = 'Keine Meldung (para_entries) gefunden – wird beim Import automatisch angelegt.';
                    }

                    if (!$dbClub) {
                        $warnings[] = 'Verein nicht in para_clubs gefunden – wird beim Import automatisch angelegt.';
                    }

                    if (!$autoSelectedId) {
                        $warnings[] = "Athlet nicht in para_athletes gefunden – wird beim Import automatisch angelegt (Status W).";
                    }

                    $timeMs = $this->support->parseTimeToMs($swimtimeStr);
                    if ($swimtimeStr !== '' && $timeMs === null) {
                        $warnings[] = "Zeitformat nicht erkannt: '{$swimtimeStr}' (Import setzt time_ms = NULL).";
                    }

                    // Splits
                    $splitsLabel = $this->buildSplitsLabel($resNode);

                    // Sportclass label
                    $strokeCode = strtoupper((string) ($event?->swimstyle?->stroke_code ?? $event?->swimstyle?->stroke ?? 'FREE'));
                    $sportClassLabel = $this->support->athleteSportClassLabelForStroke(
                        $primaryByTmId,
                        $athNode,
                        $strokeCode
                    );

                    $birthYear = null;
                    if (!empty($birthdate) && preg_match('/^\d{4}/', $birthdate, $m)) {
                        $birthYear = (int) $m[0];
                    }
                    if ($primaryByTmId?->birthdate) {
                        $birthYear = (int) $primaryByTmId->birthdate->format('Y');
                    }

                    $rows[] = [
                        'result_key' => $resultId ?: ($lenexAthleteId.'|'.$lenexEventId.'|'.$clubName),

                        'lenex_resultid' => $resultId,
                        'lenex_eventid' => $lenexEventId,

                        'event_label' => SwimstyleLabel::event($event),
                        'para_event_id' => $event?->id,

                        'lenex_athlete_id' => $lenexAthleteId,
                        'first_name' => $first,
                        'last_name' => $last,

                        'sport_class_label' => $sportClassLabel,
                        'birth_year' => $birthYear,

                        'swimtime' => $swimtimeStr,
                        'time_ms' => $timeMs,
                        'time_fmt' => $timeMs !== null ? SwimTime::format($timeMs) : ($swimtimeStr ?: '—'),

                        'splits_label' => $splitsLabel,

                        // Matching UI
                        'match_candidates' => $cands,
                        'match_selected' => $autoSelectedId,

                        // UI states
                        'invalid' => !empty($invalidReasons),
                        'invalid_reasons' => array_values(array_unique($invalidReasons)),
                        'warnings' => array_values(array_unique($warnings)),
                    ];
                }
            }

            if (!empty($rows)) {
                $clubs[] = [
                    'club_id' => $lenexClubId,
                    'club_name' => $clubName,
                    'nation' => $nation,
                    'rows' => $rows,
                    'club_match_candidates' => $clubCands,
                    'club_match_selected' => $clubAutoId,
                ];
            }
        }

        return ['clubs' => $clubs];
    }

    private function buildSplitsLabel(SimpleXMLElement $resNode): ?string
    {
        $parts = [];
        $count = 0;

        foreach (($resNode->SPLITS->SPLIT ?? []) as $splitNode) {
            $dist = isset($splitNode['distance']) ? (int) $splitNode['distance'] : null;
            $tStr = trim((string) ($splitNode['swimtime'] ?? ''));
            if (!$dist || $tStr === '') {
                continue;
            }

            $ms = $this->support->parseTimeToMs($tStr);
            $parts[] = $dist.': '.($ms !== null ? SwimTime::format($ms) : $tStr);
            $count++;
            if ($count >= 3) {
                break;
            }
        }

        if (empty($parts)) {
            return null;
        }

        $more = count(($resNode->SPLITS->SPLIT ?? [])) > 3 ? ' …' : '';
        return implode(' | ', $parts).$more;
    }
}
