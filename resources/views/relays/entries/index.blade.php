@php use App\Support\SwimTime; @endphp
@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1>Staffeln – Entries</h1>
            <div class="d-flex gap-2">
                <a class="btn btn-primary" href="{{ route('meets.relay-entries.create', $meet) }}">Neu</a>
                <a class="btn btn-link" href="{{ route('meets.show', $meet) }}">Zurück zum Meeting</a>
            </div>
        </div>

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="mb-2">
            <strong>Meeting:</strong> {{ $meet->name }}
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Event</th>
                    <th>Verein</th>
                    <th>Relay #</th>
                    <th>Result</th>
                    <th>Members</th>
                    <th style="width:220px;"></th>
                </tr>
                </thead>
                <tbody>
                @forelse($entries as $e)
                    <tr>
                        <td>{{ $e->id }}</td>
                        <td>
                            @if($e->event)
                                <div><strong>{{ $e->event->number ?? 'Event' }}</strong></div>
                                <small class="text-muted">
                                    {{ $e->event->swimstyle?->distance }}m {{ $e->event->swimstyle?->stroke }}
                                </small>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $e->club?->nameDe ?? $e->club?->shortNameDe ?? '—' }}</td>
                        <td>{{ $e->lenex_relay_number ?? '—' }}</td>
                        <td>
                            @if($e->result)
                                {{ $e->result->time_ms !== null ? SwimTime::format($e->result->time_ms) : '—' }}
                                <small class="text-muted">({{ $e->result->status }})</small>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $e->members?->count() ?? 0 }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary"
                               href="{{ route('meets.relay-entries.show', [$meet, $e]) }}">Ansehen</a>
                            <a class="btn btn-sm btn-outline-secondary"
                               href="{{ route('meets.relay-entries.edit', [$meet, $e]) }}">Edit</a>
                            <form class="d-inline" method="POST"
                                  action="{{ route('meets.relay-entries.destroy', [$meet, $e]) }}"
                                  onsubmit="return confirm('Relay Entry wirklich löschen?');">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Löschen</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-muted">Keine Relay Entries vorhanden.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
