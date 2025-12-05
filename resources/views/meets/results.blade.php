@php
    use App\Models\ParaMeet;
    use App\Models\ParaResult;
    use Illuminate\Support\Collection;

    /** @var Collection|ParaResult[] $results */
    /** @var ParaMeet $meet */
@endphp

@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Ergebnisse – {{ $meet->name }}</h1>

        <p>
            <a href="{{ route('meets.show', $meet) }}" class="btn btn-secondary btn-sm">
                Zurück zum Meeting
            </a>
        </p>

        @if ($results->isEmpty())
            <div class="alert alert-info">
                Für dieses Meeting sind noch keine Resultate vorhanden.
            </div>
        @else
            @php
                /** @var Collection $grouped */
                $grouped = $results->groupBy('entry.event.id');
            @endphp

            @foreach ($grouped as $eventId => $eventResults)
                @php
                    /** @var ParaResult|null $first */
                    $first = $eventResults->first();
                    $event = $first?->entry?->event;
                    $swim  = $event?->swimstyle;

                    $eventLabel = sprintf(
                        '%s – %sm %s',
                        $event?->number ?? 'Event ?',
                        $swim?->distance ?? '?',
                        $swim?->stroke ?? ''
                    );
                @endphp

                <div class="card mb-4">
                    <div class="card-header">
                        <strong>{{ $eventLabel }}</strong>
                    </div>

                    <div class="card-body p-0">
                        <table class="table table-striped mb-0 table-sm">
                            <thead>
                            <tr>
                                <th>Lauf</th>
                                <th>Bahn</th>
                                <th>Platz</th>
                                <th>Schwimmer</th>
                                <th>Verein</th>
                                <th>Zeit</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($eventResults as $result)
                                @php
                                    $entry   = $result->entry;
                                    $athlete = $entry?->athlete;
                                    $club    = $entry?->club;
                                @endphp

                                <tr>
                                    <td>{{ $result->heat ?? '-' }}</td>
                                    <td>{{ $result->lane ?? '-' }}</td>
                                    <td>{{ $result->rank ?? '-' }}</td>
                                    <td>
                                        @if ($athlete)
                                            <a href="{{ route('athletes.show', $athlete) }}">
                                                {{ trim(($athlete->lastName ?? '').', '.($athlete->firstName ?? '')) ?: '—' }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if ($club)
                                            {{ $club->nameDe ?? '—' }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $result->time_formatted ?? '-' }}</td>
                                    <td>
                                        @if ($result->status && $result->status !== 'OK')
                                            <span class="badge bg-danger">{{ $result->status }}</span>
                                        @endif
                                    </td>
                                </tr>

                                @if ($result->splits->isNotEmpty())
                                    <tr>
                                        <td colspan="7">
                                            <strong>Splits:</strong>
                                            @foreach ($result->splits as $split)
                                                <span class="me-3">
                                                        {{ $split->distance }}m:
                                                        {{ number_format($split->time_ms / 1000, 2, ',', '') }} s
                                                    </span>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
@endsection
