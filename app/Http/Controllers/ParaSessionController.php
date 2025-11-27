<?php

namespace App\Http\Controllers;

use App\Models\ParaMeet;
use App\Models\ParaSession;
use Illuminate\Http\Request;

class ParaSessionController extends Controller
{
    public function create(ParaMeet $meet)
    {
        $session = new ParaSession();
        $session->para_meet_id = $meet->id;

        return view('sessions.create', compact('meet', 'session'));
    }

    public function store(Request $request, ParaMeet $meet)
    {
        $data = $request->validate([
            'number'       => ['required', 'integer'],
            'date'         => ['nullable', 'date'],
            'start_time'   => ['nullable'],
            'warmup_from'  => ['nullable'],
            'warmup_until' => ['nullable'],
        ]);

        $data['para_meet_id'] = $meet->id;

        ParaSession::create($data);

        return redirect()
            ->route('meets.show', $meet)
            ->with('status', 'Session hinzugefügt.');
    }

    public function edit(ParaSession $session)
    {
        $meet = $session->meet;

        return view('sessions.edit', compact('meet', 'session'));
    }

    public function update(Request $request, ParaSession $session)
    {
        $data = $request->validate([
            'number'       => ['required', 'integer'],
            'date'         => ['nullable', 'date'],
            'start_time'   => ['nullable'],
            'warmup_from'  => ['nullable'],
            'warmup_until' => ['nullable'],
        ]);

        $session->update($data);

        return redirect()
            ->route('meets.show', $session->para_meet_id)
            ->with('status', 'Session aktualisiert.');
    }

    public function destroy(ParaSession $session)
    {
        $meetId = $session->para_meet_id;
        $session->delete();

        return redirect()
            ->route('meets.show', $meetId)
            ->with('status', 'Session gelöscht.');
    }
}
