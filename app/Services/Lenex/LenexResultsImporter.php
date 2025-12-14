<?php

namespace App\Services\Lenex;

use App\Models\ParaEntry;
use App\Models\ParaMeet;
use App\Models\ParaResult;
use App\Models\ParaSplit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
     * Importiert ausgewählte Individual-Results (Athleten) inkl. Splits.
     *
     * $selectedResultKeys enthält resultid (LENEX) oder den Fallback-Key aus dem Preview:
     *   resultid ?: "{athleteid}|{eventid}|{clubName}"
     * @throws Throwable
     */
    public function importSelected(string $path, ParaMeet $meet, array $selectedResultKeys): void
    {
        $selected = array_fill_keys(array_map('strval', $selectedResultKeys), true);

        $root = $this->lenex->loadLenexRootFromPath($path);

        $meetNode = $root->MEETS->MEET[0] ?? null;
        if (!$meetNode instanceof SimpleXMLElement) {
            throw new RuntimeException('Keine MEET-Definition im LENEX gefunden (Results).');
        }

        // Entries indexieren: (lenex_athleteid|lenex_eventid) -> ParaEntry
        $entries = ParaEntry::query()
            ->where('para_meet_id', $meet->id)
            ->get();

        $entryByKey = [];
        foreach ($entries as $e) {
            $aid = (string) ($e->lenex_athleteid ?? '');
            $eid = (string) ($e->lenex_eventid ?? '');
            if ($aid !== '' && $eid !== '') {
                $entryByKey[$aid.'|'.$eid] = $e;
            }
        }

        DB::transaction(function () use ($meet, $meetNode, $selected, $entryByKey) {
            foreach (($meetNode->CLUBS->CLUB ?? []) as $clubNode) {
                $clubName = trim((string) ($clubNode['name'] ?? ''));

                foreach (($clubNode->ATHLETES->ATHLETE ?? []) as $athNode) {
                    $lenexAthleteId = (string) ($athNode['athleteid'] ?? '');
                    foreach (($athNode->RESULTS->RESULT ?? []) as $resNode) {
                        $lenexEventId = (string) ($resNode['eventid'] ?? '');
                        $lenexResultId = (string) ($resNode['resultid'] ?? '');

                        $key = $lenexResultId ?: ($lenexAthleteId.'|'.$lenexEventId.'|'.$clubName);

                        if (!isset($selected[$key])) {
                            continue;
                        }

                        $entry = ($lenexAthleteId !== '' && $lenexEventId !== '')
                            ? ($entryByKey[$lenexAthleteId.'|'.$lenexEventId] ?? null)
                            : null;

                        if (!$entry) {
                            // Preview sollte das bereits als invalid markieren, daher hier skip
                            continue;
                        }

                        $swimtimeStr = (string) ($resNode['swimtime'] ?? '');
                        $timeMs = $this->lenex->parseTimeToMs($swimtimeStr);

                        $rank = null;
                        foreach (['rank', 'place', 'position'] as $attr) {
                            if (isset($resNode[$attr]) && (string) $resNode[$attr] !== '') {
                                $rank = (int) $resNode[$attr];
                                break;
                            }
                        }

                        $status = isset($resNode['status']) ? (string) $resNode['status'] : null;

                        // ParaResult upsert (Schlüssel: para_entry_id)
                        $resultWhere = ['para_entry_id' => $entry->id];

                        $resultData = $this->filterColumns('para_results', array_filter([
                            'para_meet_id' => $meet->id,
                            'para_event_id' => $entry->para_event_id ?? null,
                            'para_session_id' => $entry->para_session_id ?? null,
                            'para_event_agegroup_id' => $entry->para_event_agegroup_id ?? null,
                            'para_athlete_id' => $entry->para_athlete_id ?? null,
                            'para_club_id' => $entry->para_club_id ?? null,

                            'lenex_resultid' => $lenexResultId ?: null,
                            'lenex_eventid' => $lenexEventId ?: null,
                            'lenex_athleteid' => $lenexAthleteId ?: null,

                            'time' => $swimtimeStr ?: null,
                            'time_ms' => $timeMs,

                            'rank' => $rank,
                            'status' => $status,
                        ], fn($v) => $v !== null));

                        $result = ParaResult::updateOrCreate($resultWhere, $resultData);

                        // Splits neu schreiben
                        ParaSplit::where('para_result_id', $result->id)->delete();

                        $splitIndex = 1;
                        foreach (($resNode->SPLITS->SPLIT ?? []) as $splitNode) {
                            $dist = isset($splitNode['distance']) ? (int) $splitNode['distance'] : null;
                            $tStr = (string) ($splitNode['swimtime'] ?? '');
                            $tMs = $this->lenex->parseTimeToMs($tStr);

                            $splitData = $this->filterColumns('para_splits', array_filter([
                                'para_result_id' => $result->id,
                                'split_index' => $splitIndex,
                                'distance' => $dist,
                                'distance_m' => $dist,
                                'time' => $tStr ?: null,
                                'time_ms' => $tMs,
                            ], fn($v) => $v !== null));

                            ParaSplit::create($splitData);
                            $splitIndex++;
                        }
                    }
                }
            }
        });
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
}
