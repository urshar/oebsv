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

        <table class="table table-striped">
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
            @forelse($classifications as $classification)
                <tr>
                    <td>
                        {{ optional($classification->classification_date)->format('d.m.Y') }}
                    </td>
                    <td>{{ $classification->location }}</td>
                    <td>{{ $classification->is_international ? 'Ja' : 'Nein' }}</td>
                    <td>
                        @if($classification->sportclass_s || $classification->sportclass_sb || $classification->sportclass_sm)
                            S: {{ $classification->sportclass_s }} |
                            SB: {{ $classification->sportclass_sb }} |
                            SM: {{ $classification->sportclass_sm }}
                        @endif
                    </td>
                    <td>{{ $classification->wps_license }}</td>
                    <td>{{ $classification->status }}</td>
                    <td>
                        Tech 1:
                        @if($classification->tech1)
                            {{ $classification->tech1->fullName }}
                        @else
                            -
                        @endif
                        <br>
                        Tech 2:
                        @if($classification->tech2)
                            {{ $classification->tech2->fullName }}
                        @else
                            -
                        @endif
                        <br>
                        Med:
                        @if($classification->med)
                            {{ $classification->med->fullName }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('classifications.edit', $classification) }}"
                           class="btn btn-sm btn-outline-primary">
                            Bearbeiten
                        </a>
                        <form action="{{ route('classifications.destroy', $classification) }}"
                              method="POST"
                              class="d-inline"
                              onsubmit="return confirm('Klassifikation wirklich löschen?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                Löschen
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">Noch keine Klassifikationen.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
