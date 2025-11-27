@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Athleten</h1>

        <div class="d-flex justify-content-between mb-3">
            <form method="GET" class="d-flex">
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm me-2" placeholder="Suche...">
                <button class="btn btn-sm btn-outline-secondary">Suchen</button>
            </form>

            <a href="{{ route('athletes.create') }}" class="btn btn-sm btn-primary">
                Athlet anlegen
            </a>
        </div>

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <table class="table table-sm table-bordered align-middle">
            <thead>
            <tr>
                <th>Name</th>
                <th>Nation</th>
                <th>Verein</th>
                <th>Lizenz</th>
                <th>Sportklasse (aktuell)</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach($athletes as $athlete)
                <tr>
                    <td>{{ $athlete->lastName }} {{ $athlete->firstName }}</td>
                    <td>{{ $athlete->nation?->ioc ?? $athlete->nation?->nameEn }}</td>
                    <td>{{ $athlete->club?->nameDe ?? $athlete->club?->nameEn }}</td>
                    <td>{{ $athlete->license }}</td>
                    <td>
                        @php
                            $parts = [];
                            if ($athlete->sportclass_s)  $parts[] = 'S: '.$athlete->sportclass_s;
                            if ($athlete->sportclass_sb) $parts[] = 'SB: '.$athlete->sportclass_sb;
                            if ($athlete->sportclass_sm) $parts[] = 'SM: '.$athlete->sportclass_sm;
                        @endphp
                        {{ implode(' | ', $parts) ?: '–' }}
                    </td>
                    <td class="text-end">
                        <a href="{{ route('athletes.show', $athlete) }}" class="btn btn-sm btn-outline-primary">Details</a>
                        <a href="{{ route('athletes.edit', $athlete) }}" class="btn btn-sm btn-outline-secondary">Bearbeiten</a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        {{ $athletes->links() }}
    </div>
@endsection
