<?php

namespace App\Services\Lenex\Preview;

use App\Models\ParaClub;
use App\Models\ParaEntry;
use App\Models\ParaMeet;
use App\Models\Swimstyle;
use App\Services\Lenex\LenexEntryIndex;
use App\Support\SwimTime;
use SimpleXMLElement;

readonly class LenexResultsPreviewService
{
    public function __construct(
        private LenexPreviewSupport $support
    ) {
    }

    /**
     * Returns:
     * [
     *   'clubs' => [
     *      ['nation'=>..., 'club_name'=>..., 'rows'=> [...]],
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

        // DB: Events + Swimstyle (für Label)
        $meet->load('sessions.events.swimstyle');

        $eventByLenexId = [];
        foreach ($meet->sessions as $session) {
            foreach ($session->events as $event) {
                if (!empty($event->lenex_eventid)) {
                    $eventByLenexId[(string) $event->lenex_eventid] = $event;
                }
            }
        }

        // --- Backfill swimstyle_id aus der aktuellen LENEX-Datei (falls Events im DB swimstyle_id NULL haben) ---
        $eventMetaByLenexId = [];

        /** @var SimpleXMLElement|null $meetNode */
        $meetNode = $root->MEETS->MEET[0] ?? null;

        if ($meetNode instanceof SimpleXMLElement && isset($meetNode->SESSIONS)) {
            foreach ($meetNode->SESSIONS->SESSION ?? [] as $sessionNode) {
                if (!isset($sessionNode->EVENTS)) {
                    continue;
                }

                foreach ($sessionNode->EVENTS->EVENT ?? [] as $eventNode) {
                    $eid = (string) ($eventNode['eventid'] ?? '');
                    $ss = $eventNode->SWIMSTYLE ?? null;

                    if ($eid === '' || !($ss instanceof SimpleXMLElement)) {
                        continue;
                    }

                    $distance = (int) ($ss['distance'] ?? 0);
                    $relaycount = (int) ($ss['relaycount'] ?? 1);
                    $strokeCode = strtoupper(trim((string) ($ss['stroke'] ?? '')));

                    if ($distance <= 0 || $strokeCode === '') {
                        continue;
                    }

                    if ($relaycount <= 0) {
                        $relaycount = 1;
                    }

                    $eventMetaByLenexId[$eid] = [
                        'distance' => $distance,
                        'relaycount' => $relaycount,
                        'stroke_code' => $strokeCode,
                    ];
                }
            }
        }

        if (!empty($eventMetaByLenexId)) {
            // Swimstyles einmal laden und indexieren
            $styleIndex = [];
            foreach (Swimstyle::query()->get() as $s) {
                $key = ((int) $s->distance).'|'.((int) $s->relaycount).'|'.strtoupper((string) $s->stroke_code);
                $styleIndex[$key] = $s;

                // Fallback: manche DBs haben stroke statt stroke_code genutzt
                $key2 = ((int) $s->distance).'|'.((int) $s->relaycount).'|'.strtoupper((string) $s->stroke);
                if (!isset($styleIndex[$key2])) {
                    $styleIndex[$key2] = $s;
                }
            }

            foreach ($eventByLenexId as $lenexEventId => $event) {
                if (!empty($event->swimstyle_id)) {
                    continue;
                }

                $meta = $eventMetaByLenexId[$lenexEventId] ?? null;
                if (!$meta) {
                    continue;
                }

                $k = $meta['distance'].'|'.$meta['relaycount'].'|'.$meta['stroke_code'];
                $swimstyle = $styleIndex[$k] ?? null;

                if ($swimstyle) {
                    $event->swimstyle_id = $swimstyle->id;
                    $event->save();
                    $event->setRelation('swimstyle', $swimstyle);
                }
            }
        }

        // DB: Entries index (meet + lenex_athleteid|lenex_eventid) => ParaEntry
        $entries = ParaEntry::query()
            ->where('para_meet_id', $meet->id)
            ->with(['athlete.club', 'club'])   // <- wichtig
            ->get();

        $entryByAthEvent = LenexEntryIndex::byAthleteEvent($entries);

        $clubs = [];

        foreach (($meetNode->CLUBS->CLUB ?? []) as $clubNode) {
            /** @var SimpleXMLElement $clubNode */
            $clubName = trim((string) ($clubNode['name'] ?? ''));
            $nation = trim((string) ($clubNode['nation'] ?? ''));
            $lenexClubId = (string) ($clubNode['clubid'] ?? '');

            $rows = [];

            foreach (($clubNode->ATHLETES->ATHLETE ?? []) as $athNode) {
                /** @var SimpleXMLElement $athNode */
                $lenexAthleteId = (string) ($athNode['athleteid'] ?? '');

                $first = trim((string) ($athNode['firstname'] ?? $athNode['givenname'] ?? ''));
                $last = trim((string) ($athNode['lastname'] ?? $athNode['familyname'] ?? ''));
                $birthdate = trim((string) ($athNode['birthdate'] ?? ''));

                foreach (($athNode->RESULTS->RESULT ?? []) as $resNode) {
                    /** @var SimpleXMLElement $resNode */

                    // ✅ kein Duplicate mehr: Result Context über Support
                    $ctx = $this->support->initResultContext($resNode, $eventByLenexId);

                    $resultId = $ctx['resultId'];
                    $lenexEventId = $ctx['lenexEventId'];
                    $swimtimeStr = $ctx['swimtimeStr'];
                    $invalidReasons = $ctx['invalidReasons'];
                    $event = $ctx['event'];

                    // Entry finden (Entries müssen vorher importiert sein)
                    $entry = null;
                    if ($lenexAthleteId !== '' && $lenexEventId !== '') {
                        $entry = $entryByAthEvent[$lenexAthleteId.'|'.$lenexEventId] ?? null;
                    }
                    if (!$entry) {
                        $invalidReasons[] = 'Keine passende Meldung (para_entries) gefunden – bitte zuerst Entries importieren';
                    }

                    if ($entry && !empty($entry->para_club_id)) {
                        if (!ParaClub::query()->whereKey($entry->para_club_id)->exists()) {
                            $invalidReasons[] = 'Verein der Meldung im System nicht gefunden (para_clubs)';
                        }
                    }

                    // Athlete match: prefer Entry->athlete, fallback by name (+ birthdate)
                    $dbAthlete = $entry?->athlete;

                    if (!$dbAthlete) {
                        $dbAthlete = $this->support->findAthleteByName($first, $last, $birthdate);
                    }

                    if (!$dbAthlete) {
                        $invalidReasons[] = "Athlet $last, $first ($lenexAthleteId) nicht in para_athletes gefunden";
                    }

                    // optional sanity: athlete club differs from entry club
                    if ($dbAthlete && $entry && $entry->para_club_id && $dbAthlete->para_club_id
                        && (int) $dbAthlete->para_club_id !== (int) $entry->para_club_id) {
                        $invalidReasons[] = 'Athlet ist im System bei anderem Verein als die Meldung';
                    }

                    $timeMs = $this->support->parseTimeToMs($swimtimeStr);

                    // Splits (für spätere Auswertungen wichtig)
                    $splits = [];
                    $splitIndex = 1;
                    foreach (($resNode->SPLITS->SPLIT ?? []) as $splitNode) {
                        /** @var SimpleXMLElement $splitNode */
                        $dist = isset($splitNode['distance']) ? (int) $splitNode['distance'] : null;
                        $tStr = trim((string) ($splitNode['swimtime'] ?? ''));
                        $tMs = $this->support->parseTimeToMs($tStr);

                        $splits[] = [
                            'split_index' => $splitIndex++,
                            'distance' => $dist,
                            'swimtime' => $tStr,
                            'time_ms' => $tMs,
                            'time_fmt' => $tMs !== null ? SwimTime::format($tMs) : ($tStr ?: '—'),
                        ];
                    }

                    // Fallback: wenn RESULT keine swimtime hat, nimm die Zeit aus dem letzten Split
                    if ($timeMs === null && !empty($splits)) {
                        $last = $splits[count($splits) - 1];

                        if (!empty($last['time_ms'])) {
                            $timeMs = (int) $last['time_ms'];
                        } elseif (!empty($last['swimtime'])) {
                            $timeMs = $this->support->parseTimeToMs((string) $last['swimtime']);
                        }

                        if (($swimtimeStr ?? '') === '' && !empty($last['swimtime'])) {
                            $swimtimeStr = (string) $last['swimtime'];
                        }
                    }

                    // Geburtsjahr (DB bevorzugt, sonst LENEX birthdate)
                    $birthYear = null;
                    if ($dbAthlete?->birthdate) {
                        $birthYear = (int) $dbAthlete->birthdate->format('Y');
                    } elseif (!empty($birthdate) && preg_match('/^\d{4}/', $birthdate, $m)) {
                        $birthYear = (int) $m[0];
                    }

                    // Sportklasse (DB bevorzugt; passe Feldnamen an deine DB an)
                    $sportClass = null;
                    if ($dbAthlete) {
                        foreach (['sport_class', 'sportclass', 'sportClass'] as $attr) {
                            if (!empty($dbAthlete->{$attr}) && ctype_digit((string) $dbAthlete->{$attr})) {
                                $sportClass = (int) $dbAthlete->{$attr};
                                break;
                            }
                        }
                    }

                    $strokeCode = strtoupper((string) ($event?->swimstyle?->stroke ?? 'FREE'));
                    $sportClassLabel = $this->support->athleteSportClassLabelForStroke($dbAthlete, $athNode,
                        $strokeCode);

                    $rows[] = [
                        'result_key' => $resultId ?: ($lenexAthleteId.'|'.$lenexEventId.'|'.$clubName),
                        'lenex_resultid' => $resultId,
                        'lenex_eventid' => $lenexEventId,

                        'event_label' => SwimstyleLabel::event($event),
                        'para_event_id' => $event?->id,
                        'swimstyle_id' => $event?->swimstyle_id,

                        'lenex_athlete_id' => $lenexAthleteId,
                        'first_name' => $first,
                        'last_name' => $last,
                        'db_athlete_id' => $dbAthlete?->id,
                        'athlete_filter_key' => $lenexAthleteId,

                        'sport_class' => $sportClass,
                        'sport_class_label' => $sportClassLabel,
                        'birth_year' => $birthYear,

                        'entry_id' => $entry?->id,

                        'swimtime' => $swimtimeStr,
                        'time_ms' => $timeMs,
                        'time_fmt' => $timeMs !== null ? SwimTime::format($timeMs) : ($swimtimeStr ?: '—'),

                        'splits' => $splits,

                        'invalid' => !empty($invalidReasons),
                        'invalid_reasons' => array_values(array_unique($invalidReasons)),
                    ];
                }
            }

            if (!empty($rows)) {
                $clubs[] = [
                    'club_id' => $lenexClubId,
                    'club_name' => $clubName,
                    'nation' => $nation,
                    'rows' => $rows,
                ];
            }
        }

        return ['clubs' => $clubs];
    }
}
