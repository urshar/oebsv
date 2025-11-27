<?php

namespace App\Http\Controllers;

use App\Models\ParaAthlete;
use App\Models\ParaAthleteClassification;
use Illuminate\Http\Request;

class ParaAthleteClassificationController extends Controller
{
    public function index(ParaAthlete $athlete)
    {
        $athlete->load('classifications');

        return view('athletes.classifications.index', [
            'athlete'        => $athlete,
            'classifications'=> $athlete->classifications,
        ]);
    }

    public function create(ParaAthlete $athlete)
    {
        $classification = new ParaAthleteClassification();

        return view('athletes.classifications.create', compact('athlete', 'classification'));
    }

    public function store(Request $request, ParaAthlete $athlete)
    {
        $data = $this->validateData($request);

        $data['para_athlete_id'] = $athlete->id;

        ParaAthleteClassification::create($data);

        $athlete->syncFromActiveClassification();

        return redirect()
            ->route('athletes.show', $athlete)
            ->with('status', 'Klassifikation angelegt.');
    }

    public function edit(ParaAthleteClassification $classification)
    {
        $athlete = $classification->athlete;

        return view('athletes.classifications.edit', compact('athlete', 'classification'));
    }

    public function update(Request $request, ParaAthleteClassification $classification)
    {
        $data = $this->validateData($request);

        $classification->update($data);

        $classification->athlete->syncFromActiveClassification();

        return redirect()
            ->route('athletes.show', $classification->athlete)
            ->with('status', 'Klassifikation aktualisiert.');
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

    protected function validateData(Request $request): array
    {
        return $request->validate([
                'classification_date'   => ['nullable', 'date'],
                'location'              => ['nullable', 'string', 'max:150'],
                'is_international'      => ['sometimes', 'boolean'],
                'wps_license'           => ['nullable', 'string', 'max:50'],
                'sportclass_s'          => ['nullable', 'string', 'max:10'],
                'sportclass_sb'         => ['nullable', 'string', 'max:10'],
                'sportclass_sm'         => ['nullable', 'string', 'max:10'],
                'sportclass_exception'  => ['nullable', 'string', 'max:50'],
                'status'                => ['nullable', 'string', 'max:50'],
                'tech_classifier_1'     => ['nullable', 'string', 'max:150'],
                'tech_classifier_2'     => ['nullable', 'string', 'max:150'],
                'med_classifier'        => ['nullable', 'string', 'max:150'],
                'notes'                 => ['nullable', 'string'],
            ]) + [
                'is_international' => $request->boolean('is_international'),
            ];
    }
}
