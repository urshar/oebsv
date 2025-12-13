<?php

namespace App\Http\Controllers;

use App\Models\ParaAthlete;
use App\Models\ParaClub;
use App\Models\ParaEntry;
use App\Models\ParaMeet;
use App\Services\Lenex\LenexImportService;
use App\Services\Lenex\LenexRelayImporter;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Random\RandomException;
use RuntimeException;
use Schema;
use SimpleXMLElement;
use Throwable;

class LenexRelayImportController extends Controller
{
    public function create(ParaMeet $meet): View
    {
        return view('lenex.relays-upload', compact('meet'));
    }

    /**
     * @throws Exception
     */
    public function preview(Request $request, ParaMeet $meet): View
    {
        $file = $request->validate([
            'lenex_file' => ['required', 'file', 'max:51200'],
        ])['lenex_file'];

        $fullPath = $this->storeUploadedLenex($file);

        /** @var LenexImportService $lenex */
        $xml = app(LenexImportService::class)->readLenexXml($fullPath);
        $root = new SimpleXMLElement($xml);

        $meetNode = $root->MEETS->MEET[0] ?? null;
        if (!$meetNode instanceof SimpleXMLElement) {
            throw new RuntimeException('Keine MEET-Definition im LENEX (Relays) gefunden.');
        }

        $meet->load('sessions.events.swimstyle');

        // Map: LENEX eventid -> ParaEvent
        $eventByLenexId = [];
        foreach ($meet->sessions as $session) {
            foreach ($session->events as $event) {
                if (!empty($event->lenex_eventid)) {
                    $eventByLenexId[(string) $event->lenex_eventid] = $event;
                }
            }
        }

        // Sammle alle athleteids aus RELAYPOSITIONS (für DB-lookup via ParaEntry.lenex_athleteid)
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
            ->whereIn('lenex_athleteid', $allPosIds)
            ->with('athlete')
            ->get()
            ->keyBy('lenex_athleteid');

        $clubs = [];

        foreach (($meetNode->CLUBS->CLUB ?? []) as $clubNode) {
            $lenexClubId = (string) ($clubNode['clubid'] ?? '');
            $clubName = trim((string) ($clubNode['name'] ?? ''));
            $nation = trim((string) ($clubNode['nation'] ?? ''));

            $existingClub = $this->findExistingClub($lenexClubId, $clubName);

            // LENEX Athletes des Clubs indexieren
            $athNodeById = [];
            foreach (($clubNode->ATHLETES->ATHLETE ?? []) as $athNode) {
                $aid = (string) ($athNode['athleteid'] ?? '');
                if ($aid !== '') {
                    $athNodeById[$aid] = $athNode;
                }
            }

            $relayRows = [];

            foreach (($clubNode->RELAYS->RELAY ?? []) as $relayNode) {
                $relayNumber = (string) ($relayNode['number'] ?? '');
                $relayGender = (string) ($relayNode['gender'] ?? '');

                foreach (($relayNode->RESULTS->RESULT ?? []) as $resultNode) {
                    $resultId = (string) ($resultNode['resultid'] ?? '');
                    $lenexEventId = (string) ($resultNode['eventid'] ?? '');

                    $invalidReasons = [];

                    $event = $lenexEventId !== '' ? ($eventByLenexId[$lenexEventId] ?? null) : null;
                    if (!$event) {
                        $invalidReasons[] = 'Event nicht im Meeting vorhanden';
                    } elseif (empty($event->is_relay)) {
                        $invalidReasons[] = 'Event ist kein Relay-Event';
                    }

                    if (!$existingClub) {
                        $invalidReasons[] = 'Verein im System nicht gefunden';
                    }

                    // Prüfe Members
                    $positions = [];
                    foreach (($resultNode->RELAYPOSITIONS->RELAYPOSITION ?? []) as $posNode) {
                        $aid = (string) ($posNode['athleteid'] ?? '');
                        $leg = (int) ($posNode['number'] ?? 0);

                        $inLenexClub = $aid !== '' && isset($athNodeById[$aid]);
                        if (!$inLenexClub) {
                            $invalidReasons[] = "Athlete {$aid} gehört im LENEX nicht zu diesem Verein";
                        }

                        $athNode = $inLenexClub ? $athNodeById[$aid] : null;
                        $first = $athNode ? trim((string) ($athNode['firstname'] ?? $athNode['givenname'] ?? '')) : '';
                        $last = $athNode ? trim((string) ($athNode['lastname'] ?? $athNode['familyname'] ?? '')) : '';
                        $birthdate = $athNode ? trim((string) ($athNode['birthdate'] ?? '')) : '';

                        // DB-Mapping (prefer ParaEntry.lenex_athleteid)
                        $mappedEntry = $aid !== '' ? ($entriesByLenexAthleteId[$aid] ?? null) : null;
                        $dbAthlete = $mappedEntry?->athlete;

                        // Fallback by name (+ birthdate)
                        if (!$dbAthlete && $first && $last) {
                            $dbAthlete = ParaAthlete::query()
                                ->whereRaw('LOWER("firstName") = LOWER(?)', [$first])
                                ->whereRaw('LOWER("lastName") = LOWER(?)', [$last])
                                ->when($birthdate !== '', fn($q) => $q->whereDate('birthdate', $birthdate))
                                ->first();
                        }

                        $existsInDb = (bool) $dbAthlete;
                        if (!$existsInDb) {
                            $invalidReasons[] = "Athlete {$aid} nicht in para_athletes gefunden";
                        }

                        if ($dbAthlete && $existingClub && (int) $dbAthlete->para_club_id !== (int) $existingClub->id) {
                            $invalidReasons[] = "Athlete {$dbAthlete->id} ist im System bei anderem Verein";
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
                        'points' => (string) ($resultNode['points'] ?? ''),
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

        $lenexFilePath = $fullPath;

        return view('lenex.relays-preview', compact('meet', 'clubs', 'lenexFilePath'));
    }

    /**
     * @throws RandomException
     */
    private function storeUploadedLenex(UploadedFile $file): string
    {
        $name = 'lenex_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$file->getClientOriginalExtension();
        $path = Storage::disk('local')->putFileAs('tmp/lenex', $file, $name);
        return storage_path('app/'.$path);
    }

    // -------- helpers --------
    private function findExistingClub(string $lenexClubId, string $clubName): ?ParaClub
    {
        $q = ParaClub::query();

        // Optional: wenn du para_clubs.lenex_clubid hast
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

    public function import(Request $request, ParaMeet $meet, LenexRelayImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'lenex_file_path' => ['required', 'string'],
            'selected_relays' => ['required', 'array', 'min:1'],
            'selected_relays.*' => ['string'],
        ]);

        if (!is_file($data['lenex_file_path'])) {
            return back()->withErrors(['lenex_file_path' => 'Die Lenex-Datei konnte nicht mehr gefunden werden.']);
        }

        try {
            $importer->import($data['lenex_file_path'], $meet, $data['selected_relays']);
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['selected_relays' => 'Import fehlgeschlagen: '.$e->getMessage()]);
        }

        return redirect()
            ->route('meets.show', $meet)
            ->with('status', 'Staffel-Resultate wurden importiert.');
    }
}
