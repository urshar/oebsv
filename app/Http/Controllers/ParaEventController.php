<?php

namespace App\Http\Controllers;

use App\Models\ParaEvent;
use App\Models\ParaSession;
use App\Models\Swimstyle;
use Illuminate\Http\Request;

class ParaEventController extends Controller
{
    /**
     * Show form to create a new event inside a session.
     */
    public function create(ParaSession $session)
    {
        $event = new ParaEvent();
        $event->para_session_id = $session->id;

        // for select box
        $swimstyles = Swimstyle::orderBy('distance')
            ->orderBy('stroke')
            ->orderBy('relaycount')
            ->get();

        return view('events.create', compact('session', 'event', 'swimstyles'));
    }

    /**
     * Store a new event in a session.
     */
    public function store(Request $request, ParaSession $session)
    {
        $data = $request->validate([
            'number'       => ['required', 'integer'],
            'order'        => ['nullable', 'integer'],
            'round'        => ['nullable', 'string', 'max:10'],
            'swimstyle_id' => ['required', 'exists:swimstyles,id'],
            'fee'          => ['nullable', 'numeric', 'min:0'],
            'fee_currency' => ['nullable', 'string', 'max:10'],
        ]);

        $data['para_session_id'] = $session->id;

        ParaEvent::create($data);

        return redirect()
            ->route('meets.show', $session->para_meet_id)
            ->with('status', 'Event hinzugefügt.');
    }

    /**
     * Edit an existing event.
     */
    public function edit(ParaEvent $event)
    {
        $session = $event->session;

        $swimstyles = Swimstyle::orderBy('distance')
            ->orderBy('stroke')
            ->orderBy('relaycount')
            ->get();

        return view('events.edit', compact('session', 'event', 'swimstyles'));
    }

    /**
     * Update an existing event.
     */
    public function update(Request $request, ParaEvent $event)
    {
        $data = $request->validate([
            'number'       => ['required', 'integer'],
            'order'        => ['nullable', 'integer'],
            'round'        => ['nullable', 'string', 'max:10'],
            'swimstyle_id' => ['required', 'exists:swimstyles,id'],
            'fee'          => ['nullable', 'numeric', 'min:0'],
            'fee_currency' => ['nullable', 'string', 'max:10'],
        ]);

        $event->update($data);

        return redirect()
            ->route('meets.show', $event->session->para_meet_id)
            ->with('status', 'Event aktualisiert.');
    }

    /**
     * Delete an event.
     */
    public function destroy(ParaEvent $event)
    {
        $meetId = $event->session->para_meet_id;

        $event->delete();

        return redirect()
            ->route('meets.show', $meetId)
            ->with('status', 'Event gelöscht.');
    }
}
