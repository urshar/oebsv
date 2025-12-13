<?php

namespace App\Http\Controllers;

use App\Models\ParaClub;
use App\Models\ParaEvent;
use App\Models\ParaMeet;
use App\Models\ParaRelayEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ParaRelayEntryController extends Controller
{
    public function index(ParaMeet $meet): View
    {
        $entries = ParaRelayEntry::query()
            ->where('para_meet_id', $meet->id)
            ->with(['event.swimstyle', 'club', 'result', 'members.athlete'])
            ->orderBy('para_event_id')
            ->orderBy('para_club_id')
            ->get();

        return view('relays.entries.index', compact('meet', 'entries'));
    }

    public function store(Request $request, ParaMeet $meet): RedirectResponse
    {
        $data = $request->validate([
            'para_event_id' => ['required', 'integer', 'exists:para_events,id'],
            'para_club_id' => ['required', 'integer', 'exists:para_clubs,id'],
            'lenex_relay_number' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string', 'max:10'],
            'entry_time' => ['nullable', 'string', 'max:32'],
        ]);

        $event = ParaEvent::findOrFail($data['para_event_id']);
        if (empty($event->is_relay)) {
            return back()->withErrors(['para_event_id' => 'Gewähltes Event ist kein Relay-Event.'])->withInput();
        }

        $entry = ParaRelayEntry::create([
            'para_meet_id' => $meet->id,
            'para_session_id' => $event->para_session_id ?? null,
            'para_event_id' => $event->id,
            'para_club_id' => $data['para_club_id'],
            'lenex_relay_number' => $data['lenex_relay_number'] ?? null,
            'gender' => $data['gender'] ?? null,
            'entry_time' => $data['entry_time'] ?? null,
            'entry_time_ms' => $this->parseTimeToMs($data['entry_time'] ?? null),
        ]);

        return redirect()->route('meets.relay-entries.show', [$meet, $entry])
            ->with('status', 'Relay Entry angelegt.');
    }

    public function create(ParaMeet $meet): View
    {
        $clubs = ParaClub::query()->orderBy('nameDe')->get();

        $meet->load('sessions.events.swimstyle');
        $relayEvents = $meet->sessions
            ->flatMap(fn($s) => $s->events)
            ->filter(fn($e) => !empty($e->is_relay))
            ->values();

        return view('relays.entries.create', compact('meet', 'clubs', 'relayEvents'));
    }

    private function parseTimeToMs(?string $time): ?int
    {
        $time = trim((string) $time);
        if ($time === '' || $time === 'NT') {
            return null;
        }

        $time = str_replace(',', '.', $time);

        if (!preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{2})(?:\.(\d{1,3}))?$/', $time, $m)) {
            return null;
        }

        $h = isset($m[1]) && $m[1] !== '' ? (int) $m[1] : 0;
        $min = (int) $m[2];
        $sec = (int) $m[3];
        $frac = $m[4] ?? '';

        $ms = ($h * 3600 + $min * 60 + $sec) * 1000;

        if ($frac !== '') {
            if (strlen($frac) === 1) {
                $frac .= '00';
            }
            if (strlen($frac) === 2) {
                $frac .= '0';
            }
            if (strlen($frac) > 3) {
                $frac = substr($frac, 0, 3);
            }
            $ms += (int) $frac;
        }

        return $ms;
    }

    public function show(ParaMeet $meet, ParaRelayEntry $relayEntry): View
    {
        $this->assertBelongsToMeet($meet, $relayEntry);

        $relayEntry->load([
            'event.swimstyle',
            'club',
            'result.splits',
            'members.athlete',
            'members.legSplits',
        ]);

        return view('relays.entries.show', compact('meet', 'relayEntry'));
    }

    private function assertBelongsToMeet(ParaMeet $meet, ParaRelayEntry $entry): void
    {
        abort_if((int) $entry->para_meet_id !== (int) $meet->id, 404);
    }

    public function edit(ParaMeet $meet, ParaRelayEntry $relayEntry): View
    {
        $this->assertBelongsToMeet($meet, $relayEntry);

        $clubs = ParaClub::query()->orderBy('nameDe')->get();

        $meet->load('sessions.events.swimstyle');
        $relayEvents = $meet->sessions
            ->flatMap(fn($s) => $s->events)
            ->filter(fn($e) => !empty($e->is_relay))
            ->values();

        return view('relays.entries.edit', compact('meet', 'relayEntry', 'clubs', 'relayEvents'));
    }

    // -------- helpers --------

    public function update(Request $request, ParaMeet $meet, ParaRelayEntry $relayEntry): RedirectResponse
    {
        $this->assertBelongsToMeet($meet, $relayEntry);

        $data = $request->validate([
            'para_event_id' => ['required', 'integer', 'exists:para_events,id'],
            'para_club_id' => ['required', 'integer', 'exists:para_clubs,id'],
            'lenex_relay_number' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'string', 'max:10'],
            'entry_time' => ['nullable', 'string', 'max:32'],
        ]);

        $event = ParaEvent::findOrFail($data['para_event_id']);
        if (empty($event->is_relay)) {
            return back()->withErrors(['para_event_id' => 'Gewähltes Event ist kein Relay-Event.'])->withInput();
        }

        $relayEntry->update([
            'para_session_id' => $event->para_session_id ?? null,
            'para_event_id' => $event->id,
            'para_club_id' => $data['para_club_id'],
            'lenex_relay_number' => $data['lenex_relay_number'] ?? null,
            'gender' => $data['gender'] ?? null,
            'entry_time' => $data['entry_time'] ?? null,
            'entry_time_ms' => $this->parseTimeToMs($data['entry_time'] ?? null),
        ]);

        return redirect()->route('meets.relay-entries.show', [$meet, $relayEntry])
            ->with('status', 'Relay Entry aktualisiert.');
    }

    public function destroy(ParaMeet $meet, ParaRelayEntry $relayEntry): RedirectResponse
    {
        $this->assertBelongsToMeet($meet, $relayEntry);

        $relayEntry->delete(); // cascades auf result/members/splits via FK
        return redirect()->route('meets.relay-entries.index', $meet)
            ->with('status', 'Relay Entry gelöscht.');
    }
}
