<?php

namespace App\Http\Controllers;

use App\Models\ParaAthlete;
use App\Models\ParaClub;
use App\Models\ParaMeet;
use App\Services\Lenex\LenexImportService;
use App\Services\Lenex\LenexResultImporter;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class LenexImportController extends Controller
{
    public function __construct(
        protected LenexImportService $lenexImportService
    ) {
    }

    /**
     * Formular für Meeting-Struktur-Import.
     * GET /lenex/upload
     */
    public function create(): View
    {
        $recentMeets = ParaMeet::orderByDesc('created_at')->limit(10)->get();

        return view('lenex.upload', compact('recentMeets'));
    }

    /**
     * Meeting-Struktur importieren (MEET, SESSIONS, EVENTS, AGEGROUPS).
     * POST /lenex/upload
     */
    public function store(Request $request): RedirectResponse
    {
        $file = $this->validateLenexFile($request);
        $fullPath = $this->storeUploadedFile($file);

        try {
            $meet = $this->lenexImportService->importMeetStructureFromPath($fullPath);
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->withErrors([
                    'lenex_file' => 'Import failed: '.$e->getMessage(),
                ]);
        }

        return redirect()
            ->route('lenex.upload.form')
            ->with('status', "LENEX-Strukturimport erfolgreich. Meeting: {$meet->name} ({$meet->city})");
    }

    /**
     * Gemeinsame Validierung für LENEX-Upload.
     *
     * Erlaubte Endungen: xml, lef, lxf, zip
     */
    private function validateLenexFile(Request $request): UploadedFile
    {
        $data = $request->validate([
            'lenex_file' => [
                'required',
                'file',
                function (string $attribute, $value, Closure $fail) {
                    if (!$value instanceof UploadedFile) {
                        $fail('Keine Datei hochgeladen.');
                        return;
                    }

                    $ext = strtolower($value->getClientOriginalExtension());

                    if (!in_array($ext, ['xml', 'lef', 'lxf', 'zip'], true)) {
                        $fail('Die Datei muss die Endung xml, lef, lxf oder zip haben.');
                    }
                },
            ],
        ]);

        /** @var UploadedFile $file */
        $file = $data['lenex_file'];

        return $file;
    }

    /**
     * Gemeinsame Speicherung des Uploads und Rückgabe des absoluten Pfads.
     */
    private function storeUploadedFile(UploadedFile $file): string
    {
        $relativePath = $file->store('lenex_uploads');
        return Storage::path($relativePath);
    }

    /**
     * Formular für Entries-Import zu einem bestehenden Meeting.
     * GET /meets/{meet}/lenex/entries
     */
    public function createEntries(ParaMeet $meet): View
    {
        return view('lenex.upload_entries', compact('meet'));
    }

    /**
     * Entries für ein bestehendes Meeting importieren.
     * POST /meets/{meet}/lenex/entries
     */
    public function storeEntries(Request $request, ParaMeet $meet): RedirectResponse
    {
        $file = $this->validateLenexFile($request);
        $fullPath = $this->storeUploadedFile($file);

        try {
            $this->lenexImportService->importEntriesFromPath($fullPath, $meet);
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->withErrors([
                    'lenex_file' => 'Import failed: '.$e->getMessage(),
                ]);
        }

        return redirect()
            ->route('meets.show', $meet)
            ->with('status', 'Entries erfolgreich importiert.');
    }

    /**
     * Formular zum Hochladen einer Lenex-Resultdatei für ein bestimmtes Meeting.
     * GET /meets/{meet}/lenex/results
     */
    public function createResults(ParaMeet $meet): View
    {
        return view('lenex.results-upload', compact('meet'));
    }

    /**
     * Schritt 1: Datei hochladen → Preview mit Auswahl Nation/Verein/Schwimmer.
     * POST /meets/{meet}/lenex/results/preview
     */
    public function previewResults(Request $request, ParaMeet $meet): View
    {
        $file = $this->validateLenexFile($request);
        $fullPath = $this->storeUploadedFile($file);

        $root = $this->lenexImportService->loadLenexRootFromPath($fullPath);
        $meetNode = $root->MEETS->MEET[0] ?? null;

        if (!$meetNode instanceof SimpleXMLElement) {
            throw new RuntimeException('Keine MEET-Definition im LENEX (Results) gefunden.');
        }

        $clubs = [];

        foreach ($meetNode->CLUBS->CLUB ?? [] as $clubNode) {
            $clubId = (string) ($clubNode['clubid'] ?? '');
            $clubName = (string) ($clubNode['name'] ?? '');
            $nation = (string) ($clubNode['nation'] ?? '');

            $athletes = [];

            foreach ($clubNode->ATHLETES->ATHLETE ?? [] as $athNode) {
                // nur Athleten mit RESULTs anzeigen
                if (!isset($athNode->RESULTS->RESULT)) {
                    continue;
                }

                $lenexAthleteId = (string) ($athNode['athleteid'] ?? '');
                if ($lenexAthleteId === '') {
                    continue;
                }

                $firstName = (string) ($athNode['firstname'] ?? $athNode['givenname'] ?? '');
                $lastName = (string) ($athNode['lastname'] ?? $athNode['familyname'] ?? '');
                $license = (string) ($athNode['license'] ?? '');

                $athletes[] = [
                    'lenex_athlete_id' => $lenexAthleteId,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'license' => $license,
                ];
            }

            if (!empty($athletes)) {
                $clubs[] = [
                    'club_id' => $clubId,
                    'club_name' => $clubName,
                    'nation' => $nation,
                    'athletes' => $athletes,
                ];
            }
        }

        $lenexFilePath = $fullPath;

        return view('lenex.results-preview', compact('meet', 'clubs', 'lenexFilePath'));
    }

    /**
     * Schritt 2 + 3: neue Vereine/Athleten erkennen, Zuordnung erlauben, dann importieren.
     * POST /meets/{meet}/lenex/results/import
     */
    public function importResults(
        Request $request,
        ParaMeet $meet,
        LenexResultImporter $importer
    ) {
        // WICHTIG: KEIN $request->validate() -> sonst back() auf POST-URL
        $validator = Validator::make($request->all(), [
            'lenex_file_path' => ['required', 'string'],
            'selected_athletes' => ['array'],
            'selected_athletes.*' => ['string'],
            'confirmation_step' => ['nullable', 'boolean'],
            'confirmed_new_athletes' => ['array'],
            'confirmed_new_athletes.*' => ['string'],
            'club_mapping' => ['array'],
            'club_mapping.*' => ['nullable', 'integer'],
            'athlete_mapping' => ['array'],
            'athlete_mapping.*' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            // IMMER auf Upload-Formular (GET), nicht auf die /import-POST-Route
            return redirect()
                ->route('meets.lenex.results.form', $meet)
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();
        $filePath = $data['lenex_file_path'];
        $selectedAthletes = $data['selected_athletes'] ?? [];
        $confirmationStep = (bool) ($data['confirmation_step'] ?? false);
        $confirmedNewAthletes = $data['confirmed_new_athletes'] ?? [];
        $clubMapping = $data['club_mapping'] ?? [];
        $athleteMapping = $data['athlete_mapping'] ?? [];

        // 1) Datei noch vorhanden?
        if (!is_file($filePath)) {
            return redirect()
                ->route('meets.lenex.results.form', $meet)
                ->withErrors([
                    'lenex_file_path' => 'Die Lenex-Datei konnte nicht mehr gefunden werden.',
                ]);
        }

        // 2) Irgendwelche Athleten ausgewählt?
        if (empty($selectedAthletes)) {
            return redirect()
                ->route('meets.lenex.results.form', $meet)
                ->withErrors([
                    'selected_athletes' => 'Es wurden keine Schwimmer für den Import ausgewählt.',
                ]);
        }

        // 3) XML laden
        $root = $this->lenexImportService->loadLenexRootFromPath($filePath);
        $meetNode = $root->MEETS->MEET[0] ?? null;

        if (!$meetNode instanceof SimpleXMLElement) {
            return redirect()
                ->route('meets.lenex.results.form', $meet)
                ->withErrors([
                    'lenex_file_path' => 'Keine MEET-Definition im LENEX (Results) gefunden.',
                ]);
        }

        // --- ab hier: neue Vereine/Athleten ermitteln ---

        $selectedSet = array_flip($selectedAthletes);
        $newClubsByKey = []; // clubKey => [...]
        $newAthletesById = []; // lenexAthleteId => [...]
        $existingSelectedIds = []; // lenexAthleteId[]

        foreach ($meetNode->CLUBS->CLUB ?? [] as $clubNode) {

            $clubName = (string) ($clubNode['name'] ?? '');
            $nationCode = (string) ($clubNode['nation'] ?? '');
            $clubKey = $nationCode.'|'.$clubName;

            $existingClub = ParaClub::where('nameDe', $clubName)->first();
            $clubIsNew = !$existingClub;
            $clubHasSelectedAthletes = false;

            foreach ($clubNode->ATHLETES->ATHLETE ?? [] as $athNode) {
                $lenexAthleteId = (string) ($athNode['athleteid'] ?? '');
                if ($lenexAthleteId === '' || !isset($selectedSet[$lenexAthleteId])) {
                    continue;
                }

                $clubHasSelectedAthletes = true;

                $firstName = (string) ($athNode['firstname'] ?? $athNode['givenname'] ?? '');
                $lastName = (string) ($athNode['lastname'] ?? $athNode['familyname'] ?? '');
                $birthdate = (string) ($athNode['birthdate'] ?? '');

                $athleteQuery = ParaAthlete::query()
                    ->where('firstName', $firstName)
                    ->where('lastName', $lastName);

                if ($birthdate) {
                    $athleteQuery->where('birthdate', $birthdate);
                }

                if ($existingClub) {
                    $athleteQuery->where('para_club_id', $existingClub->id);
                }

                $existingAthlete = $athleteQuery->first();

                if ($existingAthlete) {
                    $existingSelectedIds[] = $lenexAthleteId;
                } else {
                    $newAthletesById[$lenexAthleteId] = [
                        'lenexAthleteId' => $lenexAthleteId,
                        'firstName' => $firstName,
                        'lastName' => $lastName,
                        'birthdate' => $birthdate,
                        'clubName' => $clubName,
                        'nation' => $nationCode,
                        'clubKey' => $clubKey,
                    ];
                }
            }

            if ($clubIsNew && $clubHasSelectedAthletes && !isset($newClubsByKey[$clubKey])) {
                $newClubsByKey[$clubKey] = [
                    'nation' => $nationCode,
                    'name' => $clubName,
                    'clubKey' => $clubKey,
                ];
            }
        }

        $newClubs = array_values($newClubsByKey);

        // Kandidaten für neue Athleten suchen
        $newAthletes = [];
        foreach ($newAthletesById as $ath) { // $lenexId nicht mehr ungenutzt
            $candidatesQuery = ParaAthlete::query()
                ->where('lastName', $ath['lastName'])
                ->where('firstName', $ath['firstName']);

            if ($ath['birthdate']) {
                $candidatesQuery->where('birthdate', $ath['birthdate']);
            }

            $candidates = $candidatesQuery
                ->orderBy('lastName')
                ->orderBy('firstName')
                ->get();

            $ath['candidates'] = $candidates;
            $newAthletes[] = $ath;
        }

        // Schritt 2: Bestätigungsmaske anzeigen, falls nötig
        if ((!empty($newClubs) || !empty($newAthletes)) && !$confirmationStep) {
            $existingClubs = ParaClub::orderBy('nameDe')->get();

            return view('lenex.results-confirm-new', [
                'meet' => $meet,
                'lenexFilePath' => $filePath,
                'selectedAthletes' => $selectedAthletes,
                'newClubs' => $newClubs,
                'newAthletes' => $newAthletes,
                'existingClubs' => $existingClubs,
            ]);
        }

        // ab hier: zweiter Durchlauf (Bestätigung) oder es gibt nichts Neues
        $allNewIds = array_keys($newAthletesById);

        if ($confirmationStep) {
            // nur bestätigte neue Athleten
            $allowedNewIds = array_values(array_intersect($confirmedNewAthletes, $allNewIds));
        } else {
            // keine neuen Athleten vorhanden
            $allowedNewIds = [];
        }

        // finale Liste: vorhandene + bestätigte neue Athleten
        $allowedAthleteIds = array_unique(array_merge($existingSelectedIds, $allowedNewIds));

        if (empty($allowedAthleteIds)) {
            return redirect()
                ->route('meets.results', $meet)
                ->with('status', 'Es wurden keine Athleten zum Import bestätigt.');
        }

        try {
            $importer->import(
                $filePath,
                $meet,
                $allowedAthleteIds,
                $clubMapping,
                $athleteMapping
            );
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('meets.lenex.results.form', $meet)
                ->withErrors([
                    'lenex_file_path' => 'Import failed: '.$e->getMessage(),
                ]);
        }

        return redirect()
            ->route('meets.results', $meet)
            ->with('status', 'Resultate wurden importiert.');
    }


}
