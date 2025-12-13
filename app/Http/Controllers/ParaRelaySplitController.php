<?php

namespace App\Http\Controllers;

use App\Models\ParaMeet;
use App\Models\ParaRelayResult;
use App\Models\ParaRelaySplit;
use App\Support\SwimTime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ParaRelaySplitController extends Controller
{
    public function index(ParaMeet $meet, ParaRelayResult $relayResult): View
    {
        abort_if((int) $relayResult->meet->id !== (int) $meet->id, 404);

        $splits = ParaRelaySplit::query()
            ->where('para_relay_result_id', $relayResult->id)
            ->orderBy('distance')
            ->get();

        return view('relays.splits.index', compact('meet', 'relayResult', 'splits'));
    }

    public function store(Request $request, ParaMeet $meet, ParaRelayResult $relayResult): RedirectResponse
    {
        abort_if((int) $relayResult->meet->id !== (int) $meet->id, 404);

        $data = $request->validate([
            'distance' => ['required', 'integer', 'min:1'],
            'cumulative_time' => ['required', 'string', 'max:32'],
        ]);

        $ms = SwimTime::parseToMs($data['cumulative_time']);
        if ($ms === null) {
            return back()->withErrors(['cumulative_time' => 'Ungültiges Zeitformat (z.B. 01:05.32)'])->withInput();
        }

        ParaRelaySplit::updateOrCreate(
            ['para_relay_result_id' => $relayResult->id, 'distance' => $data['distance']],
            [
                'cumulative_time_ms' => $ms,
                'lenex_swimtime' => $data['cumulative_time'],
            ]
        );

        $this->recomputeTeamSplitDiffs($relayResult->id);

        return back()->with('status', 'Team-Split gespeichert.');
    }

    private function recomputeTeamSplitDiffs(int $relayResultId): void
    {
        $splits = ParaRelaySplit::query()
            ->where('para_relay_result_id', $relayResultId)
            ->orderBy('distance')
            ->get();

        $prev = 0;
        foreach ($splits as $s) {
            $diff = $s->cumulative_time_ms - $prev;
            $s->split_time_ms = $diff >= 0 ? $diff : null;
            $s->save();

            $prev = $s->cumulative_time_ms;
        }
    }

    public function edit(ParaMeet $meet, ParaRelaySplit $relaySplit): View
    {
        $relaySplit->load('result.meet');
        abort_if((int) $relaySplit->result->meet->id !== (int) $meet->id, 404);

        return view('relays.splits.edit', compact('meet', 'relaySplit'));
    }

    public function update(Request $request, ParaMeet $meet, ParaRelaySplit $relaySplit): RedirectResponse
    {
        $relaySplit->load('result.meet');
        abort_if((int) $relaySplit->result->meet->id !== (int) $meet->id, 404);

        $data = $request->validate([
            'cumulative_time' => ['required', 'string', 'max:32'],
        ]);

        $ms = SwimTime::parseToMs($data['cumulative_time']);
        if ($ms === null) {
            return back()->withErrors(['cumulative_time' => 'Ungültiges Zeitformat (z.B. 01:05.32)'])->withInput();
        }

        $relaySplit->update([
            'cumulative_time_ms' => $ms,
            'lenex_swimtime' => $data['cumulative_time'],
        ]);

        $this->recomputeTeamSplitDiffs($relaySplit->para_relay_result_id);

        return back()->with('status', 'Team-Split aktualisiert.');
    }

    public function destroy(ParaMeet $meet, ParaRelaySplit $relaySplit): RedirectResponse
    {
        $relaySplit->load('result.meet');
        abort_if((int) $relaySplit->result->meet->id !== (int) $meet->id, 404);

        $resultId = $relaySplit->para_relay_result_id;
        $relaySplit->delete();

        $this->recomputeTeamSplitDiffs($resultId);

        return back()->with('status', 'Team-Split gelöscht.');
    }
}
