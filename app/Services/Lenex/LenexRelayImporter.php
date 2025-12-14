<?php

namespace App\Services\Lenex;

use App\Models\ParaAthlete;
use App\Models\ParaClub;
use App\Models\ParaEntry;
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
     * Importiert ausgewählte Relay-Results (Staffeln).
     *
     * $selectedResultKeys enthält resultid (LENEX) oder den Fallback-Key aus dem Preview:
     *   resultid ?: "{eventid}|{relayNumber}|{clubName}"
     */
    public function import(string $path, ParaMeet $meet, array $selectedResultKeys): void
    {
        $selected = array_fill_keys(array_map('strval', $selectedResultKeys), true);

        $root = $this->lenex->loadLenexRootFromPath($path);

        $meetNode = $root->MEETS->MEET[0] ?? null;
        if (!$meetNode instanceof SimpleXMLElement) {
            throw new RuntimeException('Keine MEET-Definition im LENEX gefunden (Relays).');
        }

        if (!Schema::hasTable('para_relay_entries')) {
            throw new RuntimeException('Tabelle para_relay_entries fehlt. Bitte migrations für Relays anlegen.');
        }
        if (!Schema::hasTable('para_relay_members')) {
            // optional, aber strongly empfohlen
            throw new RuntimeException('Tabelle para_relay_members fehlt. Bitte migrations für Relay-Members anlegen.');
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

        DB::transaction(function () use ($meet, $meetNode, $selected, $eventByLenexId) {
            foreach (($meetNode->CLUBS->CLUB ?? []) as $clubNode) {
                $clubName = trim((string) ($clubNode['name'] ?? ''));
                $lenexClubId = (string) ($clubNode['clubid'] ?? '');

                $dbClub = $this->findClub($lenexClubId, $clubName);

                foreach (($clubNode->RELAYS->RELAY ?? []) as $relayNode) {
                    $relayNumber = (int) ($relayNode['number'] ?? 0);
                    $relayGender = (string) ($relayNode['gender'] ?? '');

                    foreach (($relayNode->RESULTS->RESULT ?? []) as $resNode) {
                        $lenexEventId = (string) ($resNode['eventid'] ?? '');
                        $lenexResultId = (string) ($resNode['resultid'] ?? '');

                        $key = $lenexResultId ?: ($lenexEventId.'|'.$relayNumber.'|'.$clubName);

                        if (!isset($selected[$key])) {
                            continue;
                        }

                        $event = $lenexEventId !== '' ? ($eventByLenexId[$lenexEventId] ?? null) : null;
                        if (!$event) {
                            continue; // Preview sollte invalid sein
                        }

                        if (!$dbClub) {
                            continue; // Preview invalid
                        }

                        $swimtimeStr = (string) ($resNode['swimtime'] ?? '');
                        $timeMs = $this->lenex->parseTimeToMs($swimtimeStr);

                        $agegroupId = trim((string) ($resNode['agegroupid'] ?? ''));

                        $paraEventAgegroupId = null;
                        if ($agegroupId !== '' && isset($event->agegroups)) {
                            foreach ($event->agegroups as $ag) {
                                if ((string) ($ag->lenex_agegroupid ?? '') === $agegroupId) {
                                    $paraEventAgegroupId = $ag->id;
                                    break;
                                }
                            }
                        }

                        // 1) relay entry upsert
                        $where = [
                            'para_meet_id' => $meet->id,
                        ];

                        if (Schema::hasColumn('para_relay_entries', 'lenex_resultid') && $lenexResultId !== '') {
                            $where['lenex_resultid'] = $lenexResultId;
                        } else {
                            // Fallback
                            $where += [
                                'para_event_id' => $event->id,
                                'para_club_id' => $dbClub->id,
                                'relay_number' => $relayNumber,
                            ];
                        }

                        $data = $this->filterColumns('para_relay_entries', array_filter([
                            'para_meet_id' => $meet->id,
                            'para_session_id' => $event->para_session_id ?? null,
                            'para_event_id' => $event->id,
                            'para_event_agegroup_id' => $paraEventAgegroupId,
                            'para_club_id' => $dbClub->id,
                            'swimstyle_id' => $event->swimstyle_id ?? null,

                            'lenex_eventid' => $lenexEventId ?: null,
                            'lenex_resultid' => $lenexResultId ?: null,
                            'relay_number' => $relayNumber ?: null,
                            'relay_gender' => $relayGender ?: null,

                            'time' => $swimtimeStr ?: null,
                            'time_ms' => $timeMs,
                        ], fn($v) => $v !== null));

                        DB::table('para_relay_entries')->updateOrInsert($where, $data);

                        // relay entry id holen
                        $relayEntryId = DB::table('para_relay_entries')->where($where)->value('id');
                        if (!$relayEntryId) {
                            throw new RuntimeException('Konnte para_relay_entries id nicht ermitteln.');
                        }

                        // 2) Mitglieder upsert
                        $members = [];
                        $leg = 1;
                        foreach (($relayNode->POSITIONS->POSITION ?? []) as $posNode) {
                            $lenexAthleteId = (string) ($posNode['athleteid'] ?? '');
                            $first = trim((string) ($posNode['firstname'] ?? $posNode['givenname'] ?? ''));
                            $last = trim((string) ($posNode['lastname'] ?? $posNode['familyname'] ?? ''));

                            $dbAthlete = $this->findAthleteByLenexOrEntry($meet, $lenexAthleteId, $first, $last);

                            $mWhere = ['para_relay_entry_id' => $relayEntryId, 'leg' => $leg];
                            $mData = $this->filterColumns('para_relay_members', array_filter([
                                'para_relay_entry_id' => $relayEntryId,
                                'leg' => $leg,
                                'para_athlete_id' => $dbAthlete?->id,
                                'lenex_athleteid' => $lenexAthleteId ?: null,
                                'first_name' => $first ?: null,
                                'last_name' => $last ?: null,
                            ], fn($v) => $v !== null));

                            DB::table('para_relay_members')->updateOrInsert($mWhere, $mData);

                            $members[] = ['leg' => $leg, 'para_athlete_id' => $dbAthlete?->id];
                            $leg++;
                        }

                        // 3) Splits importieren (wenn para_splits existiert + para_results existiert)
                        if (Schema::hasTable('para_results') && Schema::hasTable('para_splits')) {

                            // para_result für relay entry upsert/finden
                            $rWhere = ['para_meet_id' => $meet->id];

                            if (Schema::hasColumn('para_results', 'lenex_resultid') && $lenexResultId !== '') {
                                $rWhere['lenex_resultid'] = $lenexResultId;
                            } else {
                                $rWhere += [
                                    'para_event_id' => $event->id,
                                    'para_club_id' => $dbClub->id,
                                ];
                            }

                            $rData = $this->filterColumns('para_results', array_filter([
                                'para_meet_id' => $meet->id,
                                'para_event_id' => $event->id,
                                'para_session_id' => $event->para_session_id ?? null,
                                'para_event_agegroup_id' => $paraEventAgegroupId,
                                'para_club_id' => $dbClub->id,
                                'time' => $swimtimeStr ?: null,
                                'time_ms' => $timeMs,
                                'lenex_eventid' => $lenexEventId ?: null,
                                'lenex_resultid' => $lenexResultId ?: null,
                                'type' => 'relay',
                            ], fn($v) => $v !== null));

                            DB::table('para_results')->updateOrInsert($rWhere, $rData);

                            $paraResultId = DB::table('para_results')->where($rWhere)->value('id');
                            if ($paraResultId) {
                                // Link back (wenn Spalte existiert)
                                if (Schema::hasColumn('para_relay_entries', 'para_result_id')) {
                                    DB::table('para_relay_entries')
                                        ->where('id', $relayEntryId)
                                        ->update(['para_result_id' => $paraResultId]);
                                }

                                // Splits neu schreiben
                                DB::table('para_splits')->where('para_result_id', $paraResultId)->delete();

                                $splitIdx = 1;
                                $splits = ($resNode->SPLITS->SPLIT ?? []);
                                foreach ($splits as $i => $splitNode) {
                                    $dist = isset($splitNode['distance']) ? (int) $splitNode['distance'] : null;
                                    $tStr = (string) ($splitNode['swimtime'] ?? '');
                                    $tMs = $this->lenex->parseTimeToMs($tStr);

                                    // Leg-Mapping: häufig 1:1 mit split index
                                    $paraAthleteId = $members[$splitIdx - 1]['para_athlete_id'] ?? null;

                                    $sData = $this->filterColumns('para_splits', array_filter([
                                        'para_result_id' => $paraResultId,
                                        'split_index' => $splitIdx,
                                        'leg' => $splitIdx,
                                        'para_athlete_id' => $paraAthleteId,
                                        'distance' => $dist,
                                        'distance_m' => $dist,
                                        'time' => $tStr ?: null,
                                        'time_ms' => $tMs,
                                    ], fn($v) => $v !== null));

                                    DB::table('para_splits')->insert($sData);
                                    $splitIdx++;
                                }
                            }
                        }
                    }
                }
            }
        });
    }

    private function findClub(string $lenexClubId, string $clubName): ?ParaClub
    {
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

    private function filterColumns(string $table, array $data): array
    {
        $cols = null;
        try {
            $cols = Schema::getColumnListing($table);
        } catch (Throwable) {
            return $data;
        }

        $allowed = array_fill_keys($cols, true);

        return array_filter(
            $data,
            fn($v, $k) => isset($allowed[$k]),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function findAthleteByLenexOrEntry(
        ParaMeet $meet,
        string $lenexAthleteId,
        string $first,
        string $last
    ): ?ParaAthlete {
        if ($lenexAthleteId !== '' && Schema::hasColumn('para_athletes', 'lenex_athleteid')) {
            $a = ParaAthlete::query()->where('lenex_athleteid', $lenexAthleteId)->first();
            if ($a) {
                return $a;
            }
        }

        if ($lenexAthleteId !== '') {
            $entry = ParaEntry::query()
                ->where('para_meet_id', $meet->id)
                ->where('lenex_athleteid', $lenexAthleteId)
                ->with('athlete')
                ->first();

            if ($entry?->athlete) {
                return $entry->athlete;
            }
        }

        if ($first !== '' && $last !== '') {
            return ParaAthlete::query()
                ->whereRaw('LOWER(firstName) = ?', [mb_strtolower($first)])
                ->whereRaw('LOWER(lastName) = ?', [mb_strtolower($last)])
                ->first();
        }

        return null;
    }
}
