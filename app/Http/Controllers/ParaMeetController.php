<?php

namespace App\Http\Controllers;

use App\Models\ParaEntry;
use App\Models\ParaMeet;
use Illuminate\Http\Request;

class ParaMeetController extends Controller
{
    public function index()
    {
        $meets = ParaMeet::with('nation')
            ->orderByDesc('from_date')
            ->get();

        return view('meets.index', compact('meets'));
    }

    public function show(ParaMeet $meet)
    {
        // full structure
        $meet->load([
            'nation',
            'sessions.events.swimstyle',
            'sessions.events.agegroups',
        ]);

        return view('meets.show', compact('meet'));
    }

    // optional: editing basic meet data

    public function edit(ParaMeet $meet)
    {
        return view('meets.edit', compact('meet'));
    }

    public function update(Request $request, ParaMeet $meet)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'city'      => ['nullable', 'string', 'max:255'],
            'from_date' => ['nullable', 'date'],
            'to_date'   => ['nullable', 'date'],
        ]);

        $meet->update($data);

        return redirect()
            ->route('meets.show', $meet)
            ->with('status', 'Meeting updated.');
    }

    /**
     * Entfernt alle Entries (Meldungen) zu einem Meeting.
     */
    public function destroyEntries(ParaMeet $meet)
    {
        ParaEntry::where('para_meet_id', $meet->id)->delete();

        return redirect()
            ->route('meets.show', $meet)
            ->with('status', 'Alle Meldungen für dieses Meeting wurden gelöscht.');
    }
}
