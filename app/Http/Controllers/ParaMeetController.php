<?php

namespace App\Http\Controllers;

use App\Models\ParaEntry;
use App\Models\ParaMeet;
use App\Models\Continent;
use App\Models\ParaResult;
use Illuminate\Http\Request;

class ParaMeetController extends Controller
{
    /**
     * Übersicht aller Meetings
     */
    public function index()
    {
        $meets = ParaMeet::with('nation')
            ->orderByDesc('from_date')
            ->get();

        return view('meets.index', compact('meets'));
    }

    /**
     * Detailansicht eines Meetings
     */
    public function show(ParaMeet $meet)
    {
        // komplette Struktur laden
        $meet->load([
            'nation',
            'sessions.events.swimstyle',
            'sessions.events.agegroups',
        ]);

        return view('meets.show', compact('meet'));
    }

    /**
     * Formular zum Bearbeiten der Meeting-Stammdaten
     */

    public function edit(ParaMeet $meet)
    {
        // Kontinente mit Nationen laden, korrekt nach *NameDe/NameEn* sortiert
        $continents = Continent::with(['nations' => function ($query) {
            $query
                ->orderBy('nameDe')
                ->orderBy('nameEn');
        }])
            ->orderBy('nameDe')
            ->orderBy('nameEn')
            ->get();

        return view('meets.edit', compact('meet', 'continents'));
    }


    /**
     * Speichert die Änderungen an den Meeting-Stammdaten
     */
    public function update(Request $request, ParaMeet $meet)
    {
        $data = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'city'             => ['nullable', 'string', 'max:255'],
            'nation_id'        => ['nullable', 'exists:nations,id'], // <– nutzt deine nations-Tabelle
            'from_date'        => ['nullable', 'date'],
            'to_date'          => ['nullable', 'date', 'after_or_equal:from_date'],
            'entry_start_date' => ['nullable', 'date'],
            'entry_deadline'   => ['nullable', 'date'],
            'withdraw_until'   => ['nullable', 'date'],
        ]);

        $meet->update($data);

        return redirect()
            ->route('meets.show', $meet)
            ->with('status', 'Meeting wurde aktualisiert.');
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

    /**
     * Zeigt alle Resultate eines Meetings.
     */
    public function results(ParaMeet $meet)
    {
        // Alle Results dieses Meetings inkl. Athlet, Event, Swimstyle, Splits
        $results = ParaResult::where('para_meet_id', $meet->id)
            ->with([
                'entry.athlete',
                'entry.event.swimstyle',
                'splits',
            ])
            // Sortierung nach Event, Lauf, Bahn, Platz – nach Geschmack anpassen
            ->orderBy('para_entry_id')
            ->orderBy('heat')
            ->orderBy('lane')
            ->get();

        return view('meets.results', compact('meet', 'results'));
    }
}
