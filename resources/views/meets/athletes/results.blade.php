@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Ergebnisse {{ $athlete->lastname }}, {{ $athlete->firstname }} – {{ $meet->name }}</h1>

        @foreach($entries as $entry)
            <div class="card mb-3">
                <div class="card-header">
                    {{ optional($entry->event->swimstyle)->distance }}m
                    {{ optional($entry->event->swimstyle)->stroke }}
                    (Lauf: {{ $entry->event->number ?? '?' }})
                </div>
                <div class="card-body">
                    @forelse($entry->results as $result)
                        <p>
                            <strong>{{ $result->round ?? 'Lauf' }}</strong>:
                            Zeit {{ $result->time_formatted ?? '-' }},
                            Platz {{ $result->rank ?? '-' }},
                            Bahn {{ $result->lane ?? '-' }}
                            @if($result->status && $result->status !== 'OK')
                                <span class="badge bg-danger">{{ $result->status }}</span>
                            @endif
                        </p>

                        @if($result->splits->isNotEmpty())
                            <table class="table table-sm">
                                <thead>
                                <tr>
                                    <th>Distanz</th>
                                    <th>Zwischenzeit</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($result->splits as $split)
                                    <tr>
                                        <td>{{ $split->distance }}m</td>
                                        <td>{{ $split->time_ms }} ms</td>
                                        {{-- du kannst dir hier auch ein hübsches Format basteln --}}
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        @endif
                    @empty
                        <p>Keine Resultate vorhanden.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
@endsection
