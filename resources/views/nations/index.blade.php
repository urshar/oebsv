@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Nationen</h1>

        @if(session('status'))
            <div class="alert alert-success mt-2 mb-3">
                {{ session('status') }}
            </div>
        @endif

        <div class="mb-3">
            <a href="{{ route('nations.create') }}" class="btn btn-primary">
                Neue Nation anlegen
            </a>
        </div>

        @if($nations->isEmpty())
            <p>Keine Nationen vorhanden.</p>
        @else
            <table class="table table-bordered table-striped">
                <thead>
                <tr>
                    <th>Name (EN)</th>
                    <th>Name (DE)</th>
                    <th>IOC</th>
                    <th>ISO2</th>
                    <th>ISO3</th>
                    <th>Kontinent</th>
                    <th class="text-end">Aktionen</th>
                </tr>
                </thead>
                <tbody>
                @foreach($nations as $nation)
                    <tr>
                        <td>{{ $nation->nameEn }}</td>
                        <td>{{ $nation->nameDe }}</td>
                        <td>{{ $nation->ioc }}</td>
                        <td>{{ $nation->iso2 }}</td>
                        <td>{{ $nation->iso3 }}</td>
                        <td>{{ $nation->continent?->nameEn }}</td>
                        <td class="text-end">
                            <a href="{{ route('nations.edit', $nation) }}" class="btn btn-sm btn-secondary">
                                Bearbeiten
                            </a>
                            <form action="{{ route('nations.destroy', $nation) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Diese Nation wirklich löschen?');">
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
