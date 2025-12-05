<?php

namespace App\Http\Controllers;

use App\Models\ParaRecord;
use App\Services\Lenex\LenexRecordImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ParaRecordImportController extends Controller
{
    public function index(Request $request)
    {
        $classes = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,21];

        $selectedClass    = (int) $request->query('class', 1);
        $selectedGender   = $request->query('gender', 'M');
        $selectedCourse   = $request->query('course', 'SCM');
        $selectedCategory = $request->query('cat', 'OP');   // OP / JG

        if (! in_array($selectedClass, $classes, true)) {
            $selectedClass = 1;
        }

        $sportClass = 'S' . $selectedClass;

        $records = ParaRecord::query()
            ->with(['club', 'athlete'])
            ->where('sport_class', $sportClass)
            ->where('gender', $selectedGender)
            ->where('course', $selectedCourse)
            ->where('relaycount', 1)
            ->where('agegroup_code', $selectedCategory)
            ->orderBy('distance')
            ->orderBy('stroke')
            ->orderBy('swimtime_ms')
            ->get();

        $strokeLabels = [
            'FREE'   => 'Freistil',
            'BACK'   => 'Rücken',
            'BREAST' => 'Brust',
            'FLY'    => 'Schmetterling',
            'MEDLEY' => 'Lagen',
        ];

        return view('paraRecords.index', compact(
            'records',
            'classes',
            'selectedClass',
            'selectedGender',
            'selectedCourse',
            'selectedCategory',
            'strokeLabels'
        ));
    }


    public function __construct(
        protected LenexRecordImporter $lenexRecordImporter,
    ) {}

    /**
     * Formular für den Rekord-Import anzeigen.
     */
    public function create()
    {
        return view('paraRecords.import');
    }

    /**
     * Hochgeladenes LENEX-File verarbeiten.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'lenex_file' => ['required', 'file', 'mimes:xml,lef,lxf,zip'],
        ]);

        $file = $data['lenex_file'];

        $originalName = $file->getClientOriginalName();
        $ext          = strtolower($file->getClientOriginalExtension());
        $filename     = pathinfo($originalName, PATHINFO_FILENAME);

        // -> immer auf dem "local"-Disk (storage/app) speichern
        $storedPath = $file->storeAs(
            'lenex_imports',
            $filename.'_'.time().'.'.$ext,
            'local'    // WICHTIG
        );

        // Absoluten Pfad für den Importer holen
        $fullPath = Storage::disk('local')->path($storedPath);

        try {
            $this->lenexRecordImporter->import($fullPath);

            return redirect()
                ->route('para-records.import.create')
                ->with('status', 'Para-Rekorde wurden erfolgreich importiert.');
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withErrors([
                    'lenex_file' => 'Import fehlgeschlagen: '.$e->getMessage(),
                ])
                ->withInput();
        }
    }
}
