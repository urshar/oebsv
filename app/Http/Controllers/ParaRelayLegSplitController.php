<?php

namespace App\Http\Controllers;

use App\Models\ParaMeet;
use App\Models\ParaRelayLegSplit;
use App\Models\ParaRelayMember;
use App\Support\SwimTime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ParaRelayLegSplitController extends Controller
{
    public function index(ParaMeet $meet, ParaRelayMember $relayMember): View
    {
        $relayMember->load('entry.meet', 'athlete');
        abort_if((int) $relayMember->entry->para_meet_id !== (int) $meet->id, 404);

        $splits = ParaRelayLegSplit::query()
            ->where('para_relay_member_id', $relayMember->id)
            ->orderBy('distance_in_leg')
            ->get();

        return view('relays.leg_splits.index', compact('meet', 'relayMember', 'splits'));
    }

    public function store(Request $request, ParaMeet $meet, ParaRelayMember $relayMember): RedirectResponse
    {
        $relayMember->load('entry.meet');
        abort_if((int) $relayMember->entry->para_meet_id !== (int) $meet->id, 404);

        $data = $request->validate([
            'distance_in_leg' => ['required', 'integer', 'min:1'],
            'cumulative_time' => ['required', 'string', 'max:32'],
            'absolute_distance' => ['nullable', 'integer', 'min:1'],
        ]);

        $ms = SwimTime::parseToMs($data['cumulative_time']);
        if ($ms === null) {
            return back()->withErrors(['cumulative_time' => 'Ungültiges Zeitformat (z.B. 00:32.15)'])->withInput();
        }

        ParaRelayLegSplit::updateOrCreate(
            ['para_relay_member_id' => $relayMember->id, 'distance_in_leg' => $data['distance_in_leg']],
            [
                'cumulative_time_ms' => $ms,
                'absolute_distance' => $data['absolute_distance'] ?? null,
            ]
        );

        $this->recomputeLegSplitDiffs($relayMember->id);

        return back()->with('status', 'Leg-Split gespeichert.');
    }

    private function recomputeLegSplitDiffs(int $relayMemberId): void
    {
        $splits = ParaRelayLegSplit::query()
            ->where('para_relay_member_id', $relayMemberId)
            ->orderBy('distance_in_leg')
            ->get();

        $prev = 0;
        foreach ($splits as $s) {
            $diff = $s->cumulative_time_ms - $prev;
            $s->split_time_ms = $diff >= 0 ? $diff : null;
            $s->save();

            $prev = $s->cumulative_time_ms;
        }
    }

    public function edit(ParaMeet $meet, ParaRelayLegSplit $relayLegSplit): View
    {
        $relayLegSplit->load('member.entry.meet', 'member.athlete');
        abort_if((int) $relayLegSplit->member->entry->para_meet_id !== (int) $meet->id, 404);

        return view('relays.leg_splits.edit', compact('meet', 'relayLegSplit'));
    }

    public function update(Request $request, ParaMeet $meet, ParaRelayLegSplit $relayLegSplit): RedirectResponse
    {
        $relayLegSplit->load('member.entry.meet');
        abort_if((int) $relayLegSplit->member->entry->para_meet_id !== (int) $meet->id, 404);

        $data = $request->validate([
            'cumulative_time' => ['required', 'string', 'max:32'],
            'absolute_distance' => ['nullable', 'integer', 'min:1'],
        ]);

        $ms = SwimTime::parseToMs($data['cumulative_time']);
        if ($ms === null) {
            return back()->withErrors(['cumulative_time' => 'Ungültiges Zeitformat (z.B. 00:32.15)'])->withInput();
        }

        $relayLegSplit->update([
            'cumulative_time_ms' => $ms,
            'absolute_distance' => $data['absolute_distance'] ?? null,
        ]);

        $this->recomputeLegSplitDiffs($relayLegSplit->para_relay_member_id);

        return back()->with('status', 'Leg-Split aktualisiert.');
    }

    public function destroy(ParaMeet $meet, ParaRelayLegSplit $relayLegSplit): RedirectResponse
    {
        $relayLegSplit->load('member.entry.meet');
        abort_if((int) $relayLegSplit->member->entry->para_meet_id !== (int) $meet->id, 404);

        $memberId = $relayLegSplit->para_relay_member_id;
        $relayLegSplit->delete();

        $this->recomputeLegSplitDiffs($memberId);

        return back()->with('status', 'Leg-Split gelöscht.');
    }
}
