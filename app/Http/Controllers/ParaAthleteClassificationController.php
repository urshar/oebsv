<?php

namespace App\Http\Controllers;

use App\Models\ParaAthlete;
use App\Models\ParaAthleteClassification;
use App\Models\ParaClassifier;
use Illuminate\Http\Request;

class ParaAthleteClassificationController extends Controller
{
    public function index(ParaAthlete $athlete)
    {
        $classifications = $athlete->classifications()
            ->with('classifiers')             // wichtig wegen tech1/tech2/med
            ->orderByDesc('classification_date')
            ->get();

        return view('athletes.classifications.index', compact(
            'athlete',
            'classifications'
        ));
    }

    public function create(ParaAthlete $athlete)
    {
        $technicalClassifiers = ParaClassifier::technical()
            ->orderBy('lastName')
            ->orderBy('firstName')
            ->get();

        $medicalClassifiers = ParaClassifier::medical()
            ->orderBy('lastName')
            ->orderBy('firstName')
            ->get();

        $classification = new ParaAthleteClassification([
            'para_athlete_id' => $athlete->id,
        ]);

        return view('athletes.classifications.create', compact(
            'athlete',
            'classification',
            'technicalClassifiers',
            'medicalClassifiers',
        ));
    }

    public function store(Request $request, ParaAthlete $athlete)
    {
        $data = $this->validateClassification($request);
        $data['para_athlete_id'] = $athlete->id;

        $classification = ParaAthleteClassification::create($data);
        $this->syncClassifiers($classification, $request);

        return redirect()
            ->route('athletes.classifications.index', $athlete)
            ->with('success', 'Klassifikation wurde angelegt.');
    }

    public function edit(ParaAthleteClassification $classification)
    {
        $athlete = $classification->athlete;

        $technicalClassifiers = ParaClassifier::technical()
            ->orderBy('lastName')
            ->orderBy('firstName')
            ->get();

        $medicalClassifiers = ParaClassifier::medical()
            ->orderBy('lastName')
            ->orderBy('firstName')
            ->get();

        return view('athletes.classifications.edit', compact(
            'athlete',
            'classification',
            'technicalClassifiers',
            'medicalClassifiers'
        ));
    }

    public function update(Request $request, ParaAthleteClassification $paraAthleteClassification)
    {
        $data = $this->validateClassification($request);
        $paraAthleteClassification->update($data);

        // Klassifizierer in der Pivot-Tabelle aktualisieren
        $this->syncClassifiers($paraAthleteClassification, $request);

        // Redirect ggf. an deine Route anpassen
        return redirect()
            ->back()
            ->with('success', 'Klassifikation wurde aktualisiert.');
    }

    public function destroy(ParaAthleteClassification $classification)
    {
        $athlete = $classification->athlete;

        $classification->delete();

        $athlete->syncFromActiveClassification();

        return redirect()
            ->route('athletes.show', $athlete)
            ->with('status', 'Klassifikation gelöscht.');
    }

    /**
     * Validierung der Klassifikationsdaten
     */
    protected function validateClassification(Request $request): array
    {
        $data = $request->validate([
            'classification_date'  => ['nullable', 'date'],
            'location'             => ['nullable', 'string', 'max:150'],
            'is_international'     => ['nullable', 'boolean'],
            'wps_license'          => ['nullable', 'string', 'max:50'],

            'sportclass_s'         => ['nullable', 'string', 'max:10'],
            'sportclass_sb'        => ['nullable', 'string', 'max:10'],
            'sportclass_sm'        => ['nullable', 'string', 'max:10'],
            'sportclass_exception' => ['nullable', 'string', 'max:50'],

            'status'               => ['nullable', 'string', 'max:50'],
            'notes'                => ['nullable', 'string'],

            // IDs aus den Dropdowns
            'tech_classifier1_id'  => ['nullable', 'exists:para_classifiers,id'],
            'tech_classifier2_id'  => ['nullable', 'exists:para_classifiers,id'],
            'med_classifier_id'    => ['nullable', 'exists:para_classifiers,id'],
        ]);

        // Checkbox sauber in bool umwandeln
        $data['is_international'] = $request->boolean('is_international');

        return $data;
    }

    /**
     * TECH1 / TECH2 / MED in der Pivot-Tabelle speichern
     */
    protected function syncClassifiers(ParaAthleteClassification $classification, Request $request): void
    {
        $pivotData = [];

        if ($request->filled('tech_classifier1_id')) {
            $pivotData[$request->input('tech_classifier1_id')] = ['role' => 'TECH1'];
        }

        if ($request->filled('tech_classifier2_id')) {
            $pivotData[$request->input('tech_classifier2_id')] = ['role' => 'TECH2'];
        }

        if ($request->filled('med_classifier_id')) {
            $pivotData[$request->input('med_classifier_id')] = ['role' => 'MED'];
        }

        // ersetzt alle bisherigen Einträge für diese Klassifikation
        $classification->classifiers()->sync($pivotData);
    }
}
