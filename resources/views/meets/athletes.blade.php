@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Athleten – {{ $meet->name }}</h1>

        <p class="text-muted">
            Ort: {{ $meet->city }},
            Nation: {{ $meet->nation?->ioc ?? $meet->nation?->nameEn }}
        </p>

        <div class="mb-3">
            <a href="{{ route('meets.show', $meet) }}" class="btn btn-secondary btn-sm">
                &laquo; zurück zum Meeting
            </a>
        </div>

        @if($athletes->isEmpty())
            <p>Für dieses Meeting sind keine Meldungen vorhanden.</p>
        @else
            <table class="table table-bordered table-sm align-middle">
                <thead>
                <tr>
                    <th>Athlet</th>
                    <th>Geb.-Datum</th>
                    <th>Geschlecht</th>
                    <th>Nation</th>
                    <th>Verein</th>
                    <th>Sportklasse</th>
                    <th>Meldungen</th>
                </tr>
                </thead>
                <tbody>
                @foreach($athletes as $athlete)
                    <tr>
                        <td>
                            {{ $athlete->lastName }} {{ $athlete->firstName }}
                        </td>
                        <td>
                            {{ optional($athlete->birthdate)->format('d.m.Y') }}
                        </td>
                        <td>{{ $athlete->gender }}</td>
                        <td>{{ $athlete->nation?->ioc ?? $athlete->nation?->nameEn }}</td>
                        <td>
                            {{ $athlete->club?->nameDe ?? $athlete->club?->nameEn }}
                            @if($athlete->club?->nation)
                                ({{ $athlete->club->nation->ioc }})
                            @endif
                        </td>
                        <td>
                            {{-- HIER neu: grobe Übersicht aller Klassen --}}
                            @php
                                $parts = [];
                                if ($athlete->sportclass_s) {
                                    $parts[] = 'S: '.$athlete->sportclass_s;
                                }
                                if ($athlete->sportclass_sb) {
                                    $parts[] = 'SB: '.$athlete->sportclass_sb;
                                }
                                if ($athlete->sportclass_sm) {
                                    $parts[] = 'SM: '.$athlete->sportclass_sm;
                                }
                            @endphp

                            {{ implode(' | ', $parts) ?: '–' }}
                        </td>
                        <td>
                            @if($athlete->entries->isEmpty())
                                <span class="text-muted">keine Meldungen</span>
                            @else
                                <ul class="mb-0 ps-3">
                                    @foreach($athlete->entries as $entry)
                                        @php
                                            $ev  = $entry->event;
                                            $ses = $ev?->session;
                                            $sty = $ev?->swimstyle;
                                            $ag  = $entry->agegroup;
                                            $class = $athlete->classificationForEvent($ev);
                                        @endphp
                                        <li>
                                            {{-- Session / Event --}}
                                            @if($ses)
                                                Session {{ $ses->number }}
                                                ({{ optional($ses->date)->format('d.m.Y') }}),
                                            @endif
                                            Event {{ $ev?->number }}

                                            {{-- Swimstyle --}}
                                            @if($sty)
                                                – {{ $sty->distance }}m {{ $sty->stroke }}
                                                @if($sty->is_relay) Staffel @endif
                                            @endif

                                            {{-- Agegroup --}}
                                            @if($ag)
                                                – Agegroup: {{ $ag->name }}
                                            @endif

                                            {{-- Klassifikation für GENAU dieses Event --}}
                                            @if($class)
                                                – Klasse: {{ $class }}
                                            @endif

                                            {{-- Entrytime --}}
                                            @if($entry->entry_time)
                                                – Meldzeit: {{ $entry->entry_time }}
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
