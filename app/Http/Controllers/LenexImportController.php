<?php

namespace App\Http\Controllers;

use App\Models\ParaMeet;
use App\Services\Lenex\LenexImportService;
use App\Services\Lenex\LenexResultImporter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
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
        $file     = $this->validateLenexFile($request);
        $fullPath = $this->storeUploadedFile($file);

        try {
            $meet = $this->lenexImportService->importMeetStructureFromPath($fullPath);
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->withErrors([
                    'lenex_file' => 'Import failed: ' . $e->getMessage(),
                ]);
        }

        return redirect()
            ->route('lenex.upload.form')
            ->with('status', "LENEX-Strukturimport erfolgreich. Meeting: {$meet->name} ({$meet->city})");
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
        $file     = $this->validateLenexFile($request);
        $fullPath = $this->storeUploadedFile($file);

        try {
            $this->lenexImportService->importEntriesFromPath($fullPath, $meet);
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->withErrors([
                    'lenex_file' => 'Import failed: ' . $e->getMessage(),
                ]);
        }

        return redirect()
            ->route('meets.show', $meet)
            ->with('status', 'Entries erfolgreich importiert.');
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
                    if (! $value instanceof UploadedFile) {
                        $fail('Keine Datei hochgeladen.');
                        return;
                    }

                    $ext = strtolower($value->getClientOriginalExtension());

                    if (! in_array($ext, ['xml', 'lef', 'lxf', 'zip'], true)) {
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
        // z.B. "lenex_uploads/xyz.zip"
        $relativePath = $file->store('lenex_uploads');

        // absoluter Pfad, abhängig von deinem Filesystem-Disk
        return Storage::path($relativePath);
    }

    /**
     * Formular zum Hochladen einer Lenex-Resultdatei für ein bestimmtes Meeting.
     */
    public function createResults(ParaMeet $meet)
    {
        return view('lenex.results-upload', compact('meet'));
    }
    /**
     * Verarbeitet den Upload der Lenex-Resultdatei und importiert die Resultate.
     */

    public function storeResults(Request $request, ParaMeet $meet, LenexResultImporter $importer): RedirectResponse
    {
        $file     = $this->validateLenexFile($request);
        $fullPath = $this->storeUploadedFile($file);

        try {
            $importer->import($fullPath, $meet);
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->withErrors([
                    'lenex_file' => 'Import failed: ' . $e->getMessage(),
                ]);
        }

        return redirect()
            ->route('meets.results', $meet)
            ->with('status', 'Resultate wurden aus der Lenex-Datei importiert.');
    }

}
