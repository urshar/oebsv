<?php

namespace App\Http\Controllers;

use App\Models\ParaMeet;
use App\Services\Lenex\LenexImportService;
use App\Services\Lenex\LenexMeetStructureImportService;
use App\Services\Lenex\LenexRelayImporter;
use App\Services\Lenex\LenexResultsImporter;
use App\Services\Lenex\Preview\LenexPreviewSupport;
use App\Services\Lenex\Preview\LenexRelaysPreviewService;
use App\Services\Lenex\Preview\LenexResultsPreviewService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Random\RandomException;
use RuntimeException;
use Throwable;

class LenexMeetResultsWizardController extends Controller
{
    public function create(ParaMeet $meet): View
    {
        return view('lenex.results-wizard-upload', compact('meet'));
    }

    /**
     * @throws RandomException
     * @throws Throwable
     */
    public function preview(
        Request $request,
        ParaMeet $meet,
        LenexImportService $lenex,
        LenexMeetStructureImportService $meetStructure,
        LenexPreviewSupport $support,
        LenexResultsPreviewService $resultsPreview,
        LenexRelaysPreviewService $relaysPreview
    ): View|RedirectResponse {
        $data = $request->validate([
            'lenex_file' => ['required', 'file', 'max:51200'],
            'do_results' => ['nullable', 'boolean'],
            'do_relays' => ['nullable', 'boolean'],
        ]);

        $doResults = (bool) ($data['do_results'] ?? false);
        $doRelays = (bool) ($data['do_relays'] ?? false);

        if (!$doResults && !$doRelays) {
            return back()->withErrors([
                'do_results' => 'Bitte wähle mindestens "Athleten Results" oder "Relays" aus.',
            ]);
        }

        /** @var UploadedFile $file */
        $file = $data['lenex_file'];

        $relativePath = $support->storeUploadedLenex($file);
        $absolutePath = Storage::disk('local')->path($relativePath);

        $root = $lenex->loadLenexRootFromPath($absolutePath);

        try {
            // Wenn Meet/Struktur nicht vorhanden: zuerst anlegen/importieren
            $meetStructure->ensureMeetAndStructureForMeet($root, $meet);
        } catch (RuntimeException $e) {
            return back()->withErrors(['lenex_file' => $e->getMessage()]);
        }

        $resultsData = $doResults ? $resultsPreview->build($root, $meet) : null;
        $relaysData = $doRelays ? $relaysPreview->build($root, $meet) : null;

        return view('lenex.results-wizard-preview', [
            'meet' => $meet,
            'lenexFilePath' => $relativePath,
            'doResults' => $doResults,
            'doRelays' => $doRelays,
            'resultsData' => $resultsData,
            'relaysData' => $relaysData,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function import(
        Request $request,
        ParaMeet $meet,
        LenexResultsImporter $resultsImporter,
        LenexRelayImporter $relayImporter
    ): RedirectResponse {
        $data = $request->validate([
            'lenex_file_path' => ['required', 'string'],

            'selected_results' => ['nullable', 'array'],
            'selected_results.*' => ['string'],

            'selected_relays' => ['nullable', 'array'],
            'selected_relays.*' => ['string'],

            'athlete_match' => ['nullable', 'array'],
            'athlete_match.*' => ['nullable', 'string'],

            'club_match' => ['nullable', 'array'],
            'club_match.*' => ['nullable', 'string'],
        ]);


        $relativePath = $data['lenex_file_path'];

        if (!Storage::disk('local')->exists($relativePath)) {
            throw new RuntimeException('LENEX-Datei nicht mehr gefunden. Bitte Preview neu laden.');
        }

        $absolutePath = Storage::disk('local')->path($relativePath);

        $selectedResults = $data['selected_results'] ?? [];
        $selectedRelays = $data['selected_relays'] ?? [];

        $athleteMatch = $data['athlete_match'] ?? [];
        $clubMatch = $data['club_match'] ?? [];

        if (!empty($selectedResults)) {
            $resultsImporter->importSelected($absolutePath, $meet, $selectedResults, $athleteMatch, $clubMatch);
        }

        if (!empty($selectedRelays)) {
            $relayImporter->import($absolutePath, $meet, $selectedRelays, $athleteMatch, $clubMatch);
        }

        // optional: tmp löschen
        // Storage::disk('local')->delete($relativePath);

        return redirect()
            ->route('meets.show', $meet)
            ->with('status', 'Import abgeschlossen.');
    }
}
