<?php

namespace App\Http\Controllers;

use App\Models\ParaAthlete;
use App\Models\ParaMeet;
use App\Models\ParaRelayEntry;
use App\Models\ParaRelayMember;
use App\Support\SwimTime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ParaRelayMemberController extends Controller
{
    public function create(ParaMeet $meet, ParaRelayEntry $relayEntry): View
    {
        abort_if((int) $relayEntry->para_meet_id !== (int) $meet->id, 404);

        $athletes = ParaAthlete::query()
            ->where('para_club_id', $relayEntry->para_club_id)
            ->orderBy('lastName')
            ->orderBy('firstName')
            ->get();

        $relayMember = new ParaRelayMember();

        return view('relays.members.create', compact('meet', 'relayEntry', 'athletes', 'relayMember'));
    }

    public function store(Request $request, ParaMeet $meet, ParaRelayEntry $relayEntry): RedirectResponse
    {
        abort_if((int) $relayEntry->para_meet_id !== (int) $meet->id, 404);

        $data = $request->validate([
            'leg' => ['required', 'integer', 'min:1', 'max:20'],
            'para_athlete_id' => ['required', 'integer', 'exists:para_athletes,id'],
            'leg_distance' => ['nullable', 'integer', 'min:1'],
            'leg_stroke' => ['nullable', 'string', 'max:20'],
            'leg_time' => ['nullable', 'string', 'max:32'], // <— STRING
        ]);

        $athlete = ParaAthlete::findOrFail($data['para_athlete_id']);
        if ((int) $athlete->para_club_id !== (int) $relayEntry->para_club_id) {
            return back()->withErrors(['para_athlete_id' => 'Athlet gehört nicht zum Club der Staffel.'])->withInput();
        }

        $legTimeMs = null;
        if (!empty($data['leg_time'])) {
            $legTimeMs = SwimTime::parseToMs($data['leg_time']);
            if ($legTimeMs === null) {
                return back()->withErrors(['leg_time' => 'Ungültiges Zeitformat (z.B. 00:32.15)'])->withInput();
            }
        }

        ParaRelayMember::updateOrCreate(
            ['para_relay_entry_id' => $relayEntry->id, 'leg' => $data['leg']],
            [
                'para_athlete_id' => $athlete->id,
                'lenex_athleteid' => null,
                'leg_distance' => $data['leg_distance'] ?? null,
                'leg_stroke' => $data['leg_stroke'] ?? null,
                'leg_time_ms' => $legTimeMs,
            ]
        );

        return redirect()
            ->route('meets.relay-entries.show', [$meet, $relayEntry])
            ->with('status', 'Relay Member gespeichert.');
    }

    public function edit(ParaMeet $meet, ParaRelayMember $relayMember): View
    {
        $relayMember->load('entry');
        abort_if((int) $relayMember->entry->para_meet_id !== (int) $meet->id, 404);

        $athletes = ParaAthlete::query()
            ->where('para_club_id', $relayMember->entry->para_club_id)
            ->orderBy('lastName')
            ->orderBy('firstName')
            ->get();

        return view('relays.members.edit', compact('meet', 'relayMember', 'athletes'));
    }

    public function update(Request $request, ParaMeet $meet, ParaRelayMember $relayMember): RedirectResponse
    {
        $relayMember->load('entry');
        abort_if((int) $relayMember->entry->para_meet_id !== (int) $meet->id, 404);

        $data = $request->validate([
            'para_athlete_id' => ['required', 'integer', 'exists:para_athletes,id'],
            'leg_distance' => ['nullable', 'integer', 'min:1'],
            'leg_stroke' => ['nullable', 'string', 'max:20'],
            'leg_time' => ['nullable', 'string', 'max:32'], // <— STRING
        ]);

        $athlete = ParaAthlete::findOrFail($data['para_athlete_id']);
        if ((int) $athlete->para_club_id !== (int) $relayMember->entry->para_club_id) {
            return back()->withErrors(['para_athlete_id' => 'Athlet gehört nicht zum Club der Staffel.'])->withInput();
        }

        $legTimeMs = null;
        if (!empty($data['leg_time'])) {
            $legTimeMs = SwimTime::parseToMs($data['leg_time']);
            if ($legTimeMs === null) {
                return back()->withErrors(['leg_time' => 'Ungültiges Zeitformat (z.B. 00:32.15)'])->withInput();
            }
        }

        $relayMember->update([
            'para_athlete_id' => $athlete->id,
            'leg_distance' => $data['leg_distance'] ?? $relayMember->leg_distance,
            'leg_stroke' => $data['leg_stroke'] ?? $relayMember->leg_stroke,
            'leg_time_ms' => $legTimeMs,
        ]);

        return redirect()
            ->route('meets.relay-entries.show', [$meet, $relayMember->entry])
            ->with('status', 'Relay Member aktualisiert.');
    }

    public function destroy(ParaMeet $meet, ParaRelayMember $relayMember): RedirectResponse
    {
        $relayMember->load('entry');
        abort_if((int) $relayMember->entry->para_meet_id !== (int) $meet->id, 404);

        $entry = $relayMember->entry;
        $relayMember->delete();

        return redirect()
            ->route('meets.relay-entries.show', [$meet, $entry])
            ->with('status', 'Relay Member gelöscht.');
    }
}
