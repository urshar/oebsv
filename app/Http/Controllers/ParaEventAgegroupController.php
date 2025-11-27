<?php

namespace App\Http\Controllers;

use App\Models\ParaEvent;
use App\Models\ParaEventAgegroup;
use Illuminate\Http\Request;

class ParaEventAgegroupController extends Controller
{
    /**
     * Agegroup für ein Event anlegen (Formular).
     */
    public function create(ParaEvent $event)
    {
        $agegroup = new ParaEventAgegroup();
        $agegroup->para_event_id = $event->id;

        return view('agegroups.create', compact('event', 'agegroup'));
    }

    /**
     * Neue Agegroup speichern.
     */
    public function store(Request $request, ParaEvent $event)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'gender'       => ['nullable', 'string', 'max:1'], // F/M/X oder null
            'age_min'      => ['nullable', 'integer'],
            'age_max'      => ['nullable', 'integer'],
            'handicap_raw' => ['nullable', 'string', 'max:255'],
        ]);

        $data['para_event_id'] = $event->id;

        ParaEventAgegroup::create($data);

        return redirect()
            ->route('meets.show', $event->session->para_meet_id)
            ->with('status', 'Agegroup hinzugefügt.');
    }

    /**
     * Agegroup bearbeiten (Formular).
     */
    public function edit(ParaEventAgegroup $agegroup)
    {
        $event = $agegroup->event;

        return view('agegroups.edit', compact('event', 'agegroup'));
    }

    /**
     * Agegroup aktualisieren.
     */
    public function update(Request $request, ParaEventAgegroup $agegroup)
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'gender'       => ['nullable', 'string', 'max:1'],
            'age_min'      => ['nullable', 'integer'],
            'age_max'      => ['nullable', 'integer'],
            'handicap_raw' => ['nullable', 'string', 'max:255'],
        ]);

        $agegroup->update($data);

        return redirect()
            ->route('meets.show', $agegroup->event->session->para_meet_id)
            ->with('status', 'Agegroup aktualisiert.');
    }

    /**
     * Agegroup löschen.
     */
    public function destroy(ParaEventAgegroup $agegroup)
    {
        $meetId = $agegroup->event->session->para_meet_id;

        $agegroup->delete();

        return redirect()
            ->route('meets.show', $meetId)
            ->with('status', 'Agegroup gelöscht.');
    }
}
