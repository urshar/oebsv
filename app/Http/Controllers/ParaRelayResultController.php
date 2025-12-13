<?php

namespace App\Http\Controllers;

use App\Models\ParaMeet;
use App\Models\ParaRelayEntry;
use App\Models\ParaRelayResult;
use App\Support\SwimTime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ParaRelayResultController extends Controller
{
    public function store(Request $request, ParaMeet $meet, ParaRelayEntry $relayEntry): RedirectResponse
    {
        abort_if((int) $relayEntry->para_meet_id !== (int) $meet->id, 404);

        $data = $request->validate([
            'swimtime' => ['nullable', 'string', 'max:32'],
            'rank' => ['nullable', 'integer'],
            'heat' => ['nullable', 'integer'],
            'lane' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:20'],
            'points' => ['nullable', 'integer'],
        ]);

        $timeMs = null;
        if (!empty($data['swimtime'])) {
            $timeMs = SwimTime::parseToMs($data['swimtime']);
            if ($timeMs === null) {
                return back()
                    ->withErrors(['swimtime' => 'Ungültiges Zeitformat (z.B. 01:05.32)'])
                    ->withInput();
            }
        }

        ParaRelayResult::create([
            'para_relay_entry_id' => $relayEntry->id,
            'para_meet_id' => $meet->id,
            'time_ms' => $timeMs,
            'rank' => $data['rank'] ?? null,
            'heat' => $data['heat'] ?? null,
            'lane' => $data['lane'] ?? null,
            'status' => $data['status'] ?? 'OK',
            'points' => $data['points'] ?? null,
        ]);

        return redirect()
            ->route('meets.relay-entries.show', [$meet, $relayEntry])
            ->with('status', 'Relay Result angelegt.');
    }

    public function create(ParaMeet $meet, ParaRelayEntry $relayEntry): View
    {
        abort_if((int) $relayEntry->para_meet_id !== (int) $meet->id, 404);

        $relayResult = new ParaRelayResult();

        return view('relays.results.create', compact('meet', 'relayEntry', 'relayResult'));
    }

    public function edit(ParaMeet $meet, ParaRelayResult $relayResult): View
    {
        $relayResult->load('entry');
        abort_if((int) $relayResult->entry->para_meet_id !== (int) $meet->id, 404);

        return view('relays.results.edit', compact('meet', 'relayResult'));
    }

    public function update(Request $request, ParaMeet $meet, ParaRelayResult $relayResult): RedirectResponse
    {
        $relayResult->load('entry');
        abort_if((int) $relayResult->entry->para_meet_id !== (int) $meet->id, 404);

        $data = $request->validate([
            'swimtime' => ['nullable', 'string', 'max:32'],
            'rank' => ['nullable', 'integer'],
            'heat' => ['nullable', 'integer'],
            'lane' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:20'],
            'points' => ['nullable', 'integer'],
        ]);

        $timeMs = null;
        if (!empty($data['swimtime'])) {
            $timeMs = SwimTime::parseToMs($data['swimtime']);
            if ($timeMs === null) {
                return back()
                    ->withErrors(['swimtime' => 'Ungültiges Zeitformat (z.B. 01:05.32)'])
                    ->withInput();
            }
        }

        $relayResult->update([
            'time_ms' => $timeMs,
            'rank' => $data['rank'] ?? null,
            'heat' => $data['heat'] ?? null,
            'lane' => $data['lane'] ?? null,
            'status' => $data['status'] ?? $relayResult->status ?? 'OK',
            'points' => $data['points'] ?? null,
        ]);

        return redirect()
            ->route('meets.relay-entries.show', [$meet, $relayResult->entry])
            ->with('status', 'Relay Result aktualisiert.');
    }

    public function destroy(ParaMeet $meet, ParaRelayResult $relayResult): RedirectResponse
    {
        $relayResult->load('entry');
        abort_if((int) $relayResult->entry->para_meet_id !== (int) $meet->id, 404);

        $entry = $relayResult->entry;
        $relayResult->delete();

        return redirect()
            ->route('meets.relay-entries.show', [$meet, $entry])
            ->with('status', 'Relay Result gelöscht.');
    }
}
