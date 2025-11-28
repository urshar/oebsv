@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>{{ $athlete->lastName }} {{ $athlete->firstName }}</h1>

        @if(session('status'))
            <div class="alert alert-success mt-2">{{ session('status') }}</div>
        @endif

        <div class="mb-3">
            <a href="{{ route('athletes.index') }}" class="btn btn-sm btn-secondary">&laquo; zurück</a>
            <a href="{{ route('athletes.edit', $athlete) }}" class="btn btn-sm btn-primary">Bearbeiten</a>
            <a href="{{ route('athletes.classifications.index', $athlete) }}" class="btn btn-sm btn-outline-primary">
                Klassifikations-Historie
            </a>
            <a href="{{ route('athletes.classifications.create', $athlete) }}" class="btn btn-sm btn-outline-success">
                Neue Klassifikation
            </a>
        </div>

        <div class="row">
            <div class="col-md-6">
                <h5>Stammdaten</h5>
                <table class="table table-sm">
                    <tr><th>Geburtsdatum</th><td>{{ optional($athlete->birthdate)->format('d.m.Y') }}</td></tr>
                    <tr><th>Geschlecht</th><td>{{ $athlete->gender }}</td></tr>
                    <tr><th>Nation</th><td>{{ $athlete->nation?->ioc ?? $athlete->nation?->nameEn }}</td></tr>
                    <tr><th>Verein</th><td>{{ $athlete->club?->nameDe ?? $athlete->club?->nameEn }}</td></tr>
                    <tr><th>Lizenz</th><td>{{ $athlete->license }}</td></tr>
                    <tr><th>E-Mail</th><td>{{ $athlete->email }}</td></tr>
                    <tr><th>Telefon</th><td>{{ $athlete->phone }}</td></tr>
                </table>
            </div>

            <div class="col-md-6">
                <h5>Aktive Klassifikation</h5>
                @if($activeClassification)
                    <table class="table table-sm">
                        <tr>
                            <th>Datum</th>
                            <td>{{ optional($activeClassification->classification_date)->format('d.m.Y') }}</td>
                        </tr>
                        <tr>
                            <th>Ort</th>
                            <td>{{ $activeClassification->location }}</td>
                        </tr>
                        <tr>
                            <th>International</th>
                            <td>{{ $activeClassification->is_international ? 'Ja' : 'Nein' }}</td>
                        </tr>
                        <tr>
                            <th>WPS-Lizenz</th>
                            <td>{{ $activeClassification->wps_license ?? $athlete->license }}</td>
                        </tr>
                        <tr>
                            <th>Klassen</th>
                            <td>
                                @php
                                    $parts = [];
                                    if ($activeClassification->sportclass_s)  $parts[] = 'S: '.$activeClassification->sportclass_s;
                                    if ($activeClassification->sportclass_sb) $parts[] = 'SB: '.$activeClassification->sportclass_sb;
                                    if ($activeClassification->sportclass_sm) $parts[] = 'SM: '.$activeClassification->sportclass_sm;
                                @endphp
                                {{ implode(' | ', $parts) ?: '–' }}
                                @if($activeClassification->sportclass_exception)
                                    ({{ $activeClassification->sportclass_exception }})
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>{{ $activeClassification->status }}</td>
                        </tr>
                        <tr>
                            <th>Panel</th>
                            <td>
                                Tech 1:
                                @if($activeClassification && $activeClassification->tech1)
                                    {{ $activeClassification->tech1->fullName }}
                                @else
                                    -
                                @endif
                                <br>

                                Tech 2:
                                @if($activeClassification && $activeClassification->tech2)
                                    {{ $activeClassification->tech2->fullName }}
                                @else
                                    -
                                @endif
                                <br>

                                Med:
                                @if($activeClassification && $activeClassification->med)
                                    {{ $activeClassification->med->fullName }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        @if($activeClassification->notes)
                            <tr>
                                <th>Notizen</th>
                                <td>{{ $activeClassification->notes }}</td>
                            </tr>
                        @endif
                    </table>
                @else
                    <p class="text-muted">
                        Keine Klassifikation in der Historie gespeichert.
                        <br>Aktuelle Klassen (aus Athlete-Feldern):
                        @php
                            $parts = [];
                            if ($athlete->sportclass_s)  $parts[] = 'S: '.$athlete->sportclass_s;
                            if ($athlete->sportclass_sb) $parts[] = 'SB: '.$athlete->sportclass_sb;
                            if ($athlete->sportclass_sm) $parts[] = 'SM: '.$athlete->sportclass_sm;
                        @endphp
                        {{ implode(' | ', $parts) ?: '–' }}
                    </p>
                @endif
            </div>
        </div>
    </div>
@endsection
