<?php

namespace App\Http\Controllers;

use App\Models\ParaAthlete;
use App\Models\ParaClub;
use App\Models\ParaEntry;
use App\Models\ParaEvent;
use App\Models\ParaMeet;
use App\Services\Lenex\LenexImportService;
use App\Services\Lenex\LenexRelayImporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Random\RandomException;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class LenexRelayImportController extends Controller
{
    public function create(ParaMeet $meet): View
    {
        return view('lenex.relays-upload', compact('meet'));
    }

    /**
     * @throws RandomException
     */
    public function preview(Request $request, ParaMeet $meet, LenexImportService $lenex): View
    {
        $validated = $request->validate([
            'lenex_file' => ['required', 'file', 'max:51200'], // 50MB
        ]);

        /** @var UploadedFile $file */
        $file = $validated['lenex_file'];

        // 1) Upload speichern (RELATIVER Pfad in storage/app)
        $relativePath = $this->storeUploadedLenex($file);

        // 2) Absoluten OS-Pfad ermitteln (Windows-safe)
        $absolutePath = Storage::disk('local')->path($relativePath);

        // 3) LENEX laden (zentraler Loader)
        $root = $lenex->loadLenexRootFromPath($absolutePath);

        /** @var SimpleXMLElement|null $meetNode */
        $meetNode = $root->MEETS->MEET[0] ?? null;
        if (!$meetNode instanceof SimpleXMLElement) {
            throw new RuntimeException('Keine MEET-Definition im LENEX (Relays) gefunden.');
        }

        // 4) Globaler LENEX Athlete Index (athleteid -> ATHLETE node)
        $globalAthNodeById = [];
        foreach (($meetNode->CLUBS->CLUB ?? []) as $cNode) {
            foreach (($cNode->ATHLETES->ATHLETE ?? []) as $aNode) {
                $id = (string) ($aNode['athleteid'] ?? '');
                if ($id !== '') {
                    $globalAthNodeById[$id] = $aNode;
                }
            }
        }

        // 5) LENEX Event Meta Index (eventid -> relaycount/distance/stroke)
        $lenexEventMeta = $this->buildLenexEventMetaIndex($meetNode);

        // 6) ParaEvent Mapping aus DB (lenex_eventid -> ParaEvent) nur für dieses Meet
        $meet->load('sessions.events');

        $eventByLenexId = [];
        foreach ($meet->sessions as $session) {
            foreach ($session->events as $event) {
                if (!empty($event->lenex_eventid)) {
                    $eventByLenexId[(string) $event->lenex_eventid] = $event;
                }
            }
        }

        // 7) Alle athleteids aus RELAYPOSITIONS sammeln (für Entry-Mapping)
        $allPosIds = [];
        foreach (($meetNode->CLUBS->CLUB ?? []) as $clubNode) {
            foreach (($clubNode->RELAYS->RELAY ?? []) as $relayNode) {
                foreach (($relayNode->RESULTS->RESULT ?? []) as $resultNode) {
                    foreach (($resultNode->RELAYPOSITIONS->RELAYPOSITION ?? []) as $posNode) {
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
            ->when(!empty($allPosIds), fn($q) => $q->whereIn('lenex_athleteid', $allPosIds))
            ->with('athlete.club')
            ->get()
            ->keyBy('lenex_athleteid');

        // 8) Preview-Struktur aufbauen
        $clubs = [];

        foreach (($meetNode->CLUBS->CLUB ?? []) as $clubNode) {
            /** @var SimpleXMLElement $clubNode */
            $lenexClubId = (string) ($clubNode['clubid'] ?? '');
            $clubName = trim((string) ($clubNode['name'] ?? ''));
            $nation = trim((string) ($clubNode['nation'] ?? ''));

            $existingClub = ParaClub::findByLenexOrName($lenexClubId, $clubName);

            // ATHLETES dieses Clubs indexieren (LENEX-Clubzugehörigkeit prüfen)
            $athNodeById = [];
            foreach (($clubNode->ATHLETES->ATHLETE ?? []) as $athNode) {
                $aid = (string) ($athNode['athleteid'] ?? '');
                if ($aid !== '') {
                    $athNodeById[$aid] = $athNode;
                }
            }

            $relayRows = [];

            foreach (($clubNode->RELAYS->RELAY ?? []) as $relayNode) {
                /** @var SimpleXMLElement $relayNode */
                $relayNumber = (string) ($relayNode['number'] ?? '');
                $relayGender = (string) ($relayNode['gender'] ?? '');

                foreach (($relayNode->RESULTS->RESULT ?? []) as $resultNode) {
                    /** @var SimpleXMLElement $resultNode */
                    $resultId = (string) ($resultNode['resultid'] ?? '');
                    $lenexEventId = (string) ($resultNode['eventid'] ?? '');

                    $invalidReasons = [];

                    /** @var ParaEvent|null $event */
                    $event = $lenexEventId !== '' ? ($eventByLenexId[$lenexEventId] ?? null) : null;
                    if (!$event) {
                        $invalidReasons[] = "Event {$lenexEventId} nicht im Meeting vorhanden";
                    }

                    // Relay-Check über LENEX Meta (relaycount > 1)
                    $meta = $lenexEventMeta[$lenexEventId] ?? null;
                    $relaycount = (int) ($meta['relaycount'] ?? 0);
                    if ($relaycount <= 1) {
                        $invalidReasons[] = 'Event ist kein Relay-Event (relaycount<=1)';
                    }

                    if (!$existingClub) {
                        $invalidReasons[] = 'Verein im System nicht gefunden';
                    }

                    // Teilnehmer prüfen
                    $positions = [];

                    foreach (($resultNode->RELAYPOSITIONS->RELAYPOSITION ?? []) as $posNode) {
                        /** @var SimpleXMLElement $posNode */
                        $aid = (string) ($posNode['athleteid'] ?? '');
                        $leg = (int) ($posNode['number'] ?? 0);

                        // ✅ immer initialisieren
                        $dbAthlete = null;

                        $inLenexClub = $aid !== '' && isset($athNodeById[$aid]);

                        // Namen aus LENEX-Club-ATHLETES (falls vorhanden)
                        $athNode = $inLenexClub ? $athNodeById[$aid] : null;
                        $first = $athNode ? trim((string) ($athNode['firstname'] ?? $athNode['givenname'] ?? '')) : '';
                        $last = $athNode ? trim((string) ($athNode['lastname'] ?? $athNode['familyname'] ?? '')) : '';
                        $birthdate = $athNode ? trim((string) ($athNode['birthdate'] ?? '')) : '';

                        // Globaler Node (für Name-Label, auch wenn nicht im Club)
                        $globalAthNode = $globalAthNodeById[$aid] ?? null;

                        // Prefer: ParaEntry.lenex_athleteid -> Athlete
                        $mappedEntry = $aid !== '' ? ($entriesByLenexAthleteId[$aid] ?? null) : null;
                        $dbAthlete = $mappedEntry?->athlete;

                        // Fallback: Match by Name (+ Birthdate)
                        if (!$dbAthlete && $first !== '' && $last !== '') {
                            $dbAthlete = ParaAthlete::query()
                                ->with('club')
                                ->whereRaw('LOWER(firstName) = ?', [mb_strtolower($first)])
                                ->whereRaw('LOWER(lastName) = ?', [mb_strtolower($last)])
                                ->when($birthdate !== '', fn($q) => $q->whereDate('birthdate', $birthdate))
                                ->first();
                        }

                        // Wenn LENEX-Name fehlt, aber DB da ist: Namen auffüllen
                        if (($first === '' || $last === '') && $dbAthlete) {
                            $first = (string) $dbAthlete->firstName;
                            $last = (string) $dbAthlete->lastName;
                        }

                        $existsInDb = (bool) $dbAthlete;

                        // Warnungen mit Namen bauen
                        if (!$inLenexClub) {
                            $invalidReasons[] =
                                $this->lenexAthleteLabel($aid, $globalAthNode, $dbAthlete)
                                .' gehört im LENEX nicht zu diesem Verein';
                        }

                        if (!$existsInDb) {
                            $invalidReasons[] =
                                $this->lenexAthleteLabel($aid, $globalAthNode)
                                .' nicht in para_athletes gefunden';
                        }

                        if ($dbAthlete && $existingClub && (int) $dbAthlete->para_club_id !== (int) $existingClub->id) {
                            $otherClubName = $dbAthlete->club?->shortNameDe
                                ?? $dbAthlete->club?->nameDe
                                ?? null;

                            $invalidReasons[] =
                                $this->dbAthleteLabel($dbAthlete)
                                .' ist im System bei anderem Verein'
                                .($otherClubName ? " ({$otherClubName})" : '');
                        }

                        $positions[] = [
                            'leg' => $leg,
                            'lenex_athlete_id' => $aid,
                            'first_name' => $first,
                            'last_name' => $last,
                            'in_lenex_club' => $inLenexClub,
                            'exists_in_db' => $existsInDb,
                            'db_athlete_id' => $dbAthlete?->id,
                        ];
                    }

                    $relayRows[] = [
                        'result_id' => $resultId ?: ($nation.'|'.$clubName.'|'.$relayNumber.'|'.$lenexEventId),
                        'lenex_resultid' => $resultId,
                        'lenex_eventid' => $lenexEventId,
                        'relay_number' => $relayNumber,
                        'relay_gender' => $relayGender,
                        'swimtime' => (string) ($resultNode['swimtime'] ?? ''),
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

        $lenexFilePath = $relativePath; // RELATIV speichern!

        return view('lenex.relays-preview', compact('meet', 'clubs', 'lenexFilePath'));
    }

    /**
     * @throws RandomException
     */
    private function storeUploadedLenex(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'lxf');
        $name = 'lenex_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;

        $relativePath = $file->storeAs('tmp/lenex', $name, 'local');

        if (!$relativePath || !Storage::disk('local')->exists($relativePath)) {
            throw new RuntimeException('Upload konnte nicht in storage/app/tmp/lenex gespeichert werden.');
        }

        return $relativePath;
    }

    // ------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------

    private function buildLenexEventMetaIndex(SimpleXMLElement $meetNode): array
    {
        $meta = [];

        foreach (($meetNode->SESSIONS->SESSION ?? []) as $sessionNode) {
            foreach (($sessionNode->EVENTS->EVENT ?? []) as $eventNode) {
                $eid = (string) ($eventNode['eventid'] ?? '');
                if ($eid === '') {
                    continue;
                }

                $ss = $eventNode->SWIMSTYLE ?? null;
                if (!$ss instanceof SimpleXMLElement) {
                    $meta[$eid] = ['relaycount' => 0, 'distance' => 0, 'stroke' => null];
                    continue;
                }

                $meta[$eid] = [
                    'relaycount' => (int) ($ss['relaycount'] ?? 0),
                    'distance' => (int) ($ss['distance'] ?? 0),
                    'stroke' => (string) ($ss['stroke'] ?? ''),
                ];
            }
        }

        return $meta;
    }

    private function lenexAthleteLabel(
        string $lenexId,
        ?SimpleXMLElement $athNode,
        ?ParaAthlete $dbAthlete = null
    ): string {
        $lenexId = trim($lenexId);

        $first = $athNode ? trim((string) ($athNode['firstname'] ?? $athNode['givenname'] ?? '')) : '';
        $last = $athNode ? trim((string) ($athNode['lastname'] ?? $athNode['familyname'] ?? '')) : '';

        if (($first === '' || $last === '') && $dbAthlete) {
            $first = (string) $dbAthlete->firstName;
            $last = (string) $dbAthlete->lastName;
        }

        $name = trim(trim($last.', '.$first), ', ');
        return $name !== '' ? "LENEX#{$lenexId} ({$name})" : "LENEX#{$lenexId}";
    }

    private function dbAthleteLabel(ParaAthlete $athlete): string
    {
        $name = trim(trim(($athlete->lastName ?? '').', '.($athlete->firstName ?? '')), ', ');
        return $name !== '' ? "DB#{$athlete->id} ({$name})" : "DB#{$athlete->id}";
    }

    /**
     * @throws Throwable
     */
    public function import(Request $request, ParaMeet $meet, LenexRelayImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'lenex_file_path' => ['required', 'string'],
            'selected_relays' => ['required', 'array', 'min:1'],
            'selected_relays.*' => ['string'],
        ]);

        $relativePath = $data['lenex_file_path'];

        if (!Storage::disk('local')->exists($relativePath)) {
            return back()->withErrors([
                'lenex_file_path' => 'Die Lenex-Datei wurde nicht mehr gefunden (storage/app). Bitte Preview neu laden.',
            ]);
        }

        $absolutePath = Storage::disk('local')->path($relativePath);

        $importer->import($absolutePath, $meet, $data['selected_relays']);

        // optional: temp file löschen
        // Storage::disk('local')->delete($relativePath);

        return redirect()
            ->route('meets.show', $meet)
            ->with('status', 'Staffel-Resultate wurden importiert.');
    }
}
