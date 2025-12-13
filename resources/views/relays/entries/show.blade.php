@php
    use App\Support\SwimTime;
@endphp

@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1>Relay Entry #{{ $relayEntry->id }}</h1>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary"
                   href="{{ route('meets.relay-entries.edit', [$meet, $relayEntry]) }}">Edit</a>
                <a class="btn btn-link" href="{{ route('meets.relay-entries.index', $meet) }}">Zurück</a>
            </div>
        </div>

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <div><strong>Meeting:</strong> {{ $meet->name }}</div>
                <div><strong>Verein:</strong> {{ $relayEntry->club?->nameDe ?? $relayEntry->club?->shortNameDe ?? '—' }}
                </div>
                <div><strong>Relay
                        #:</strong> {{ $relayEntry->lenex_relay_number ?? '—' }} {{ $relayEntry->gender ?? '' }}</div>
                <div>
                    <strong>Event:</strong>
                    @if($relayEntry->event)
                        {{ $relayEntry->event->number ?? 'Event' }} – {{ $relayEntry->event->swimstyle?->distance }}
                        m {{ $relayEntry->event->swimstyle?->stroke }}
                    @else
                        —
                    @endif
                </div>
            </div>
        </div>

        {{-- Result --}}
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="mb-0">Result</h3>
            <div>
                @if(!$relayEntry->result)
                    <a class="btn btn-sm btn-primary"
                       href="{{ route('meets.relay-entries.relay-results.create', [$meet, $relayEntry]) }}">Result
                        hinzufügen</a>
                @else
                    <a class="btn btn-sm btn-outline-secondary"
                       href="{{ route('meets.relay-results.edit', [$meet, $relayEntry->result]) }}">Result
                        bearbeiten</a>
                    <form class="d-inline" method="POST"
                          action="{{ route('meets.relay-results.destroy', [$meet, $relayEntry->result]) }}"
                          onsubmit="return confirm('Result löschen?');">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Result löschen</button>
                    </form>
                @endif
            </div>
        </div>

        @if($relayEntry->result)
            <div class="card mb-3">
                <div class="card-body">
                    <div>
                        <strong>Zeit:</strong>
                        {{ $relayEntry->result->time_ms !== null ? SwimTime::format($relayEntry->result->time_ms) : '—' }}
                    </div>
                    <div><strong>Status:</strong> {{ $relayEntry->result->status ?? '—' }}</div>
                    <div><strong>Rang:</strong> {{ $relayEntry->result->rank ?? '—' }}</div>
                    <div><strong>Lauf/Bahn:</strong> {{ $relayEntry->result->heat ?? '—' }}
                        / {{ $relayEntry->result->lane ?? '—' }}</div>
                    <div><strong>Punkte:</strong> {{ $relayEntry->result->points ?? '—' }}</div>

                    <div class="mt-2">
                        <a class="btn btn-sm btn-outline-primary"
                           href="{{ route('meets.relay-results.relay-splits.index', [$meet, $relayEntry->result]) }}">
                            Team-Splits ansehen
                        </a>
                    </div>
                </div>
            </div>
        @endif

        {{-- Members --}}
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="mb-0">Teilnehmer (Legs)</h3>
            <a class="btn btn-sm btn-primary"
               href="{{ route('meets.relay-entries.relay-members.create', [$meet, $relayEntry]) }}">
                Member hinzufügen
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                <tr>
                    <th>Leg</th>
                    <th>Athlet</th>
                    <th>Leg Distance</th>
                    <th>Leg Time</th>
                    <th style="width:260px;"></th>
                </tr>
                </thead>
                <tbody>
                @forelse($relayEntry->members as $m)
                    <tr>
                        <td>{{ $m->leg }}</td>
                        <td>
                            @if($m->athlete)
                                {{ $m->athlete->lastName }}, {{ $m->athlete->firstName }}
                                <small class="text-muted">#{{ $m->athlete->id }}</small>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $m->leg_distance ?? '—' }}</td>
                        <td>
                            {{ $m->leg_time_ms !== null ? SwimTime::format($m->leg_time_ms) : '—' }}
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary"
                               href="{{ route('meets.relay-members.relay-leg-splits.index', [$meet, $m]) }}">
                                Leg-Splits
                            </a>
                            <a class="btn btn-sm btn-outline-secondary"
                               href="{{ route('meets.relay-members.edit', [$meet, $m]) }}">
                                Edit
                            </a>
                            <form class="d-inline" method="POST"
                                  action="{{ route('meets.relay-members.destroy', [$meet, $m]) }}"
                                  onsubmit="return confirm('Member löschen?');">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Löschen</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-muted">Keine Teilnehmer erfasst.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
