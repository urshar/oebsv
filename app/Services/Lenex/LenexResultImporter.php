<?php

namespace App\Services\Lenex;

use App\Models\ParaAthlete;
use App\Models\ParaClub;
use App\Models\ParaEntry;
use App\Models\ParaEvent;
use App\Models\ParaMeet;
use App\Models\ParaResult;
use App\Models\ParaSplit;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class LenexResultImporter
{
    public function __construct(
        protected LenexImportService $lenexImportService,
        protected NationResolver $nationResolver,
        protected ClubResolver $clubResolver,
        protected AthleteResolver $athleteResolver,
    ) {
    }

    /**
     * Importiert Resultate für ein bestimmtes Meeting aus einer LENEX-Datei.
     *
     * @param  string  $filePath  Pfad zur Lenex-Datei
     * @param  ParaMeet  $meet  Meeting, in das importiert wird
     * @param  array<string>|null  $allowedAthleteIds  optionale Liste von LENEX-ATHLETEIDs,
     *                                                nur diese Athleten werden importiert
     * @param  array<string,int>  $clubMapping  Mapping clubKey ("IOC|Name") → para_clubs.id
     * @param  array<string,int>  $athleteMapping  Mapping lenexAthleteId → para_athletes.id
     *
     * @throws Throwable
     */
    public function import(
        string $filePath,
        ParaMeet $meet,
        ?array $allowedAthleteIds = null,
        array $clubMapping = [],
        array $athleteMapping = [],
    ): void {
        // gleiche XML/LXF/ZIP-Logik wie Struktur/Entries
        $root = $this->lenexImportService->loadLenexRootFromPath($filePath);

        $meetNode = $root->MEETS->MEET[0] ?? null;
        if (!$meetNode instanceof SimpleXMLElement) {
            throw new RuntimeException('Keine MEET-Definition im LENEX (Results) gefunden.');
        }

        // Events dieses Meetings laden (inkl. lenex_eventid)
        $meet->load('sessions.events');

        $eventByLenexId = [];
        foreach ($meet->sessions as $session) {
            foreach ($session->events as $event) {
                if ($event->lenex_eventid) {
                    $eventByLenexId[(string) $event->lenex_eventid] = $event;
                }
            }
        }

        // 1) Mapping HEATID -> Laufnummer
        $heatNumberById = [];
        foreach ($meetNode->SESSIONS->SESSION ?? [] as $sessionNode) {
            foreach ($sessionNode->EVENTS->EVENT ?? [] as $eventNode) {
                foreach ($eventNode->HEATS->HEAT ?? [] as $heatNode) {
                    $heatId = (string) ($heatNode['heatid'] ?? '');
                    if ($heatId === '') {
                        continue;
                    }
                    $heatNumberById[$heatId] = (int) ($heatNode['number'] ?? 0);
                }
            }
        }

        // 2) Mapping RESULTID -> Platz (aus AGEGROUPS/RANKINGS/RANKING)
        $rankingByResultId = [];
        foreach ($meetNode->SESSIONS->SESSION ?? [] as $sessionNode) {
            foreach ($sessionNode->EVENTS->EVENT ?? [] as $eventNode) {
                foreach ($eventNode->AGEGROUPS->AGEGROUP ?? [] as $ageNode) {
                    foreach ($ageNode->RANKINGS->RANKING ?? [] as $rankingNode) {
                        $resultId = (string) ($rankingNode['resultid'] ?? '');
                        if ($resultId === '') {
                            continue;
                        }

                        $order = (int) ($rankingNode['order'] ?? 999);
                        $place = isset($rankingNode['place'])
                            ? (int) $rankingNode['place']
                            : null;

                        // pro Result nur das Ranking mit kleinstem order (meist "offiziell")
                        if (!isset($rankingByResultId[$resultId]) ||
                            $order < $rankingByResultId[$resultId]['order']) {

                            $rankingByResultId[$resultId] = [
                                'place' => $place,
                                'order' => $order,
                            ];
                        }
                    }
                }
            }
        }

        DB::transaction(function () use (
            $meetNode,
            $meet,
            $eventByLenexId,
            $heatNumberById,
            $rankingByResultId,
            $allowedAthleteIds,
            $clubMapping,
            $athleteMapping,
        ) {
            foreach ($meetNode->CLUBS->CLUB ?? [] as $clubNode) {

                $nationCode = (string) ($clubNode['nation'] ?? '');
                $nation = $nationCode !== ''
                    ? $this->nationResolver->fromLenexCode($nationCode)
                    : null;

                $clubName = (string) ($clubNode['name'] ?? '');
                // clubKey muss mit deinem Controller/Blade übereinstimmen
                $clubKey = $nationCode.'|'.$clubName;

                // Verein erst anlegen/holen, wenn klar ist,
                // dass mindestens ein Athlet dieses Clubs importiert wird
                /** @var ParaClub|null $club */
                $club = null;

                foreach ($clubNode->ATHLETES->ATHLETE ?? [] as $athNode) {

                    $lenexAthleteId = (string) ($athNode['athleteid'] ?? '');
                    if ($lenexAthleteId === '') {
                        continue;
                    }

                    // Filter: nur ausgewählte Athleten importieren, falls Liste übergeben wurde
                    if ($allowedAthleteIds !== null
                        && !in_array($lenexAthleteId, $allowedAthleteIds, true)) {
                        continue;
                    }

                    // ab hier wissen wir: dieser Athlet soll importiert werden
                    // → Verein auflösen (Mapping → bestehender Club oder neu)
                    if ($club === null) {
                        $mappedClubId = $clubMapping[$clubKey] ?? null;

                        if ($mappedClubId) {
                            $club = ParaClub::find($mappedClubId);
                        }

                        if (!$club) {
                            // Fallback: normaler Resolver (legt ggf. neuen Verein an)
                            $club = $this->clubResolver->resolveFromLenex($clubNode);
                        }
                    }

                    // Athlet-mapping: ggf. auf bestehenden Athleten zeigen
                    $mappedAthleteId = $athleteMapping[$lenexAthleteId] ?? null;

                    if ($mappedAthleteId) {
                        $athlete = ParaAthlete::find($mappedAthleteId);
                    } else {
                        $athlete = null;
                    }

                    if (!$athlete) {
                        // Fallback: normaler Resolver (legt neuen Athleten an oder matched nach deiner Logik)
                        $athlete = $this->athleteResolver->resolveFromLenex(
                            $athNode,
                            $club,
                            $nation
                        );
                    }

                    foreach ($athNode->RESULTS->RESULT ?? [] as $resultNode) {

                        $lenexEventId = (string) ($resultNode['eventid'] ?? '');
                        if ($lenexEventId === '' || !isset($eventByLenexId[$lenexEventId])) {
                            // kein passendes Event in diesem Meet
                            continue;
                        }

                        /** @var ParaEvent $eventModel */
                        $eventModel = $eventByLenexId[$lenexEventId];

                        // ENTRY suchen oder neu anlegen (pro Athlet+Event+Meet)
                        $entry = ParaEntry::firstOrCreate(
                            [
                                'para_event_id' => $eventModel->id,
                                'para_athlete_id' => $athlete->id,
                            ],
                            [
                                'para_meet_id' => $meet->id,
                                'para_session_id' => $eventModel->para_session_id,
                                'para_event_agegroup_id' => null, // kann später gesetzt werden
                                'para_club_id' => $club->id,

                                'lenex_athleteid' => $lenexAthleteId,
                                'lenex_eventid' => $lenexEventId,

                                'entry_time' => (string) ($resultNode['entrytime'] ?? null),
                                'entry_time_ms' => $this->lenexImportService->parseTimeToMs(
                                    (string) ($resultNode['entrytime'] ?? '')
                                ),
                                'course' => (string) ($resultNode['entrycourse'] ?? null),

                                'qualifying_date' => null,
                                'qualifying_meet_name' => null,
                                'qualifying_city' => null,
                                'qualifying_nation' => null,
                            ]
                        );

                        $resultId = (string) ($resultNode['resultid'] ?? '');
                        $heatId = (string) ($resultNode['heatid'] ?? '');

                        $heatNumber = isset($heatNumberById[$heatId])
                            ? $heatNumberById[$heatId]
                            : null;

                        $rankInfo = $resultId !== '' && isset($rankingByResultId[$resultId])
                            ? $rankingByResultId[$resultId]
                            : null;

                        $timeMs = $this->lenexImportService->parseTimeToMs(
                            (string) ($resultNode['swimtime'] ?? '')
                        );

                        $result = ParaResult::updateOrCreate(
                            [
                                'para_entry_id' => $entry->id,
                                'para_meet_id' => $meet->id,
                            ],
                            [
                                'time_ms' => $timeMs,
                                'reaction_time_ms' => null,
                                'rank' => $rankInfo['place'] ?? null,
                                'heat' => $heatNumber,
                                'lane' => isset($resultNode['lane'])
                                    ? (int) $resultNode['lane']
                                    : null,
                                'round' => null,
                                'status' => (string) ($resultNode['status'] ?? 'OK'),
                                'points' => isset($resultNode['points'])
                                    ? (int) $resultNode['points']
                                    : null,
                            ]
                        );

                        // SPLITS: ggf. vorhandene löschen und neu anlegen
                        $splitsNode = $resultNode->SPLITS[0] ?? null;
                        if ($splitsNode) {
                            ParaSplit::where('para_result_id', $result->id)->delete();

                            foreach ($splitsNode->SPLIT ?? [] as $splitNode) {
                                ParaSplit::create([
                                    'para_result_id' => $result->id,
                                    'distance' => (int) ($splitNode['distance'] ?? 0),
                                    'time_ms' => $this->lenexImportService->parseTimeToMs(
                                        (string) ($splitNode['swimtime'] ?? '')
                                    ),
                                ]);
                            }
                        }
                    }
                }
            }
        });
    }
}
