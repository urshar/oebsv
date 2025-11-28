@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Klassifikations-Historie – {{ $athlete->lastName }} {{ $athlete->firstName }}</h1>

        <div class="mb-3">
            <a href="{{ route('athletes.show', $athlete) }}" class="btn btn-sm btn-secondary">
                &laquo; zurück zum Athleten
            </a>
            <a href="{{ route('athletes.classifications.create', $athlete) }}" class="btn btn-sm btn-primary">
                Neue Klassifikation
            </a>
        </div>

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if($classifications->isEmpty())
            <p class="text-muted">Es sind keine Klassifikationen hinterlegt.</p>
        @else
            <table class="table table-sm table-bordered align-middle">
                <thead>
                <tr>
                    <th>Datum</th>
                    <th>Ort</th>
                    <th>International</th>
                    <th>WPS-Lizenz</th>
                    <th>Klassen</th>
                    <th>Status</th>
                    <th>Panel</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($classifications as $c)
                    <tr>
                        <td>{{ optional($c->classification_date)->format('d.m.Y') }}</td>
                        <td>{{ $c->location }}</td>
                        <td>{{ $c->is_international ? 'Ja' : 'Nein' }}</td>
                        <td>{{ $c->wps_license }}</td>
                        <td>
                            @php
                                $parts = [];
                                if ($c->sportclass_s)  $parts[] = 'S: '.$c->sportclass_s;
                                if ($c->sportclass_sb) $parts[] = 'SB: '.$c->sportclass_sb;
                                if ($c->sportclass_sm) $parts[] = 'SM: '.$c->sportclass_sm;
                            @endphp
                            {{ implode(' | ', $parts) ?: '–' }}
                            @if($c->sportclass_exception)
                                ({{ $c->sportclass_exception }})
                            @endif
                        </td>
                        <td>{{ $c->status }}</td>
                        <td>
                            Tech 1: {{ $c->tech_classifier_1 }}<br>
                            Tech 2: {{ $c->tech_classifier_2 }}<br>
                            Med: {{ $c->med_classifier }}
                        </td>
                        <td class="text-end">
                            <a href="{{ route('classifications.edit', [$athlete, $c]) }}"
                               class="btn btn-sm btn-outline-secondary">
                                Bearbeiten
                            </a>
                            <form action="{{ route('classifications.destroy', $c) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Klassifikation wirklich löschen?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">
                                    Löschen
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
