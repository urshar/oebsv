@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Kontinente</h1>

        @if(session('status'))
            <div class="alert alert-success mt-2 mb-3">
                {{ session('status') }}
            </div>
        @endif

        <div class="mb-3">
            <a href="{{ route('continents.create') }}" class="btn btn-primary">
                Neuen Kontinent anlegen
            </a>
        </div>

        @if($continents->isEmpty())
            <p>Keine Kontinente vorhanden.</p>
        @else
            <table class="table table-bordered table-striped">
                <thead>
                <tr>
                    <th>Code</th>
                    <th>Name (EN)</th>
                    <th>Name (DE)</th>
                    <th class="text-end">Aktionen</th>
                </tr>
                </thead>
                <tbody>
                @foreach($continents as $continent)
                    <tr>
                        <td>{{ $continent->code }}</td>
                        <td>{{ $continent->nameEn }}</td>
                        <td>{{ $continent->nameDe }}</td>
                        <td class="text-end">
                            <a href="{{ route('continents.edit', $continent) }}" class="btn btn-sm btn-secondary">
                                Bearbeiten
                            </a>
                            <form action="{{ route('continents.destroy', $continent) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Diesen Kontinent wirklich löschen?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">
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
