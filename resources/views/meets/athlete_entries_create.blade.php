@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Events wählen – {{ $athlete->lastName }} {{ $athlete->firstName }}</h1>

        <p class="text-muted">
            Meeting: {{ $meet->name }} ({{ $meet->city }})<br>
            Geb.: {{ optional($athlete->birthdate)->format('d.m.Y') }},
            Geschlecht: {{ $athlete->gender ?? '–' }}
        </p>

        <div class="mb-3">
            <a href="{{ route('meets.athletes.index', $meet) }}" class="btn btn-secondary btn-sm">
                &laquo; zurück zur Athletenliste
            </a>
        </div>

        @if(session('status'))
            <div class="alert alert-success mt-2 mb-3">
                {{ session('status') }}
            </div>
        @endif

        @if(empty($eligibleEvents))
            <div class="alert alert-warning">
                Für diesen Athleten wurden keine Events mit passender Agegroup gefunden
                (Alter/Geschlecht im Verhältnis zum Meetingdatum).
            </div>
        @else
            <form action="{{ route('meets.athletes.entries.store', [$meet, $athlete]) }}" method="POST" class="mt-3">
                @csrf

                <p>Bitte die Events auswählen, für die eine Meldung angelegt werden soll:</p>

                <table class="table table-sm table-bordered align-middle">
                    <thead>
                    <tr>
                        <th></th>
                        <th>Session</th>
                        <th>Event</th>
                        <th>Wettbewerb</th>
                        <th>Agegroup</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($eligibleEvents as $row)
                        @php
                            $session  = $row['session'];
                            $event    = $row['event'];
                            $agegroup = $row['agegroup'];
                            $s        = $event->swimstyle;
                        @endphp
                        <tr>
                            <td>
                                {{-- Checkbox: entries[event_id] = agegroup_id --}}
                                <input type="checkbox"
                                       name="entries[{{ $event->id }}]"
                                       value="{{ $agegroup->id }}">
                            </td>
                            <td>
                                {{ $session->number }}
                                @if($session->date)
                                    ({{ optional($session->date)->format('d.m.Y') }})
                                @endif
                            </td>
                            <td>{{ $event->number }}</td>
                            <td>
                                @if($s)
                                    {{ $s->distance }}m {{ $s->stroke }}
                                    @if($s->is_relay) Staffel @endif
                                @else
                                    –
                                @endif
                            </td>
                            <td>
                                {{ $agegroup->name }}
                                @if(!is_null($agegroup->age_min) || !is_null($agegroup->age_max))
                                    ({{ $agegroup->age_min ?? '0' }}–{{ $agegroup->age_max ?? '∞' }})
                                @endif
                                @if($agegroup->gender)
                                    – {{ $agegroup->gender }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <button type="submit" class="btn btn-primary">
                    Meldungen anlegen
                </button>
            </form>
        @endif
    </div>
@endsection
