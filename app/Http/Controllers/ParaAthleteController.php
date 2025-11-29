<?php

namespace App\Http\Controllers;

use App\Models\Nation;
use App\Models\ParaAthlete;
use App\Models\ParaClub;
use App\Models\ParaResult;
use Illuminate\Http\Request;

class ParaAthleteController extends Controller
{
    public function index(Request $request)
    {
        $q = ParaAthlete::with(['nation', 'club'])
            ->orderBy('lastName')
            ->orderBy('firstName');

        if ($search = $request->input('q')) {
            $q->where(function ($query) use ($search) {
                $query->where('firstName', 'like', "%{$search}%")
                    ->orWhere('lastName', 'like', "%{$search}%")
                    ->orWhere('license', 'like', "%{$search}%");
            });
        }

        $athletes = $q->paginate(25)->withQueryString();

        return view('athletes.index', compact('athletes'));
    }

    public function create()
    {
        $athlete = new ParaAthlete();
        $nations = Nation::orderBy('nameEn')->get();
        $clubs   = ParaClub::with('nation')->orderBy('nameDe')->get();

        return view('athletes.create', compact('athlete', 'nations', 'clubs'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'firstName'    => ['required', 'string', 'max:100'],
            'lastName'     => ['required', 'string', 'max:100'],
            'gender'       => ['nullable', 'string', 'max:1'],
            'birthdate'    => ['nullable', 'date'],
            'license'      => ['nullable', 'string', 'max:50', 'unique:para_athletes,license'],
            'para_club_id' => ['nullable', 'exists:para_clubs,id'],
            'nation_id'    => ['nullable', 'exists:nations,id'],
            'email'        => ['nullable', 'email', 'max:190'],
            'phone'        => ['nullable', 'string', 'max:50'],
        ]);

        $athlete = ParaAthlete::create($data);

        return redirect()
            ->route('athletes.show', $athlete)
            ->with('status', 'Athlet angelegt.');
    }

    public function show(ParaAthlete $athlete)
    {
        $athlete->load(['nation', 'club', 'classifications.classifiers']);

        $activeClassification = $athlete->activeClassification();

        return view('athletes.show', compact('athlete', 'activeClassification'));
    }

    public function edit(ParaAthlete $athlete)
    {
        $nations = Nation::orderBy('nameEn')->get();
        $clubs   = ParaClub::with('nation')->orderBy('nameDe')->get();

        return view('athletes.edit', compact('athlete', 'nations', 'clubs'));
    }

    public function update(Request $request, ParaAthlete $athlete)
    {
        $data = $request->validate([
            'firstName'    => ['required', 'string', 'max:100'],
            'lastName'     => ['required', 'string', 'max:100'],
            'gender'       => ['nullable', 'string', 'max:1'],
            'birthdate'    => ['nullable', 'date'],
            'license'      => ['nullable', 'string', 'max:50', 'unique:para_athletes,license,' . $athlete->id],
            'para_club_id' => ['nullable', 'exists:para_clubs,id'],
            'nation_id'    => ['nullable', 'exists:nations,id'],
            'email'        => ['nullable', 'email', 'max:190'],
            'phone'        => ['nullable', 'string', 'max:50'],
        ]);

        $athlete->update($data);

        return redirect()
            ->route('athletes.show', $athlete)
            ->with('status', 'Athlet aktualisiert.');
    }

    public function destroy(ParaAthlete $athlete)
    {
        $athlete->delete();

        return redirect()
            ->route('athletes.index')
            ->with('status', 'Athlet gelöscht.');
    }

    public function bestTimes(ParaAthlete $athlete)
    {
        // SCM
        $bestScm = ParaResult::query()
            ->whereHas('entry', function ($q) use ($athlete) {
                $q->where('para_athlete_id', $athlete->id)
                    ->whereHas('meet', fn($qm) => $qm->where('course', 'SCM'));
            })
            ->with(['entry.event.swimstyle'])
            ->selectRaw('MIN(time_ms) as best_time_ms, para_entry_id')
            ->groupBy('para_entry_id') // ggf. auf swimstyle_id gruppieren, wenn vorhanden
            ->get();

        // LCM
        $bestLcm = ParaResult::query()
            ->whereHas('entry', function ($q) use ($athlete) {
                $q->where('para_athlete_id', $athlete->id)
                    ->whereHas('meet', fn($qm) => $qm->where('course', 'LCM'));
            })
            ->with(['entry.event.swimstyle'])
            ->selectRaw('MIN(time_ms) as best_time_ms, para_entry_id')
            ->groupBy('para_entry_id')
            ->get();

        return view('athletes.best-times', compact('athlete', 'bestScm', 'bestLcm'));
    }
}
