@extends('layouts.app')

@section('title', 'Rekord-Import: Kandidaten')

@section('content')
    <div class="container mt-4">
        <h1 class="mb-3">Rekord-Import: offene Kandidaten</h1>

        @if (session('status'))
            <div class="alert alert-success">
                {{ session('status') }}
            </div>
        @endif

        @if ($candidates->isEmpty())
            <div class="alert alert-info">
                Derzeit gibt es keine offenen Import-Kandidaten.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Athlete</th>
                        <th>Club</th>
                        <th>Event</th>
                        <th>Zeit</th>
                        <th>Fehler</th>
                        <th>Quelle</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($candidates as $cand)
                        <tr>
                            <td>{{ $cand->id }}</td>
                            <td>
                                {{ $cand->athlete_lastname }}, {{ $cand->athlete_firstname }}
                                @if($cand->athlete_birthdate)
                                    <small class="text-muted">
                                        ({{ \Illuminate\Support\Carbon::parse($cand->athlete_birthdate)->format('d.m.Y') }})
                                    </small>
                                @endif
                            </td>
                            <td>
                                {{ $cand->club_name }}
                            </td>
                            <td>
                                {{ $cand->distance }}m {{ $cand->stroke }}
                                <small class="text-muted">
                                    ({{ $cand->course }}, {{ $cand->sport_class }})
                                </small>
                            </td>
                            <td>
                                {{ number_format($cand->swimtime_ms / 1000, 2) }} s
                            </td>
                            <td>
                                @if($cand->missing_athlete)
                                    <span class="badge bg-danger">Athlete fehlt</span>
                                @endif
                                @if($cand->missing_club)
                                    <span class="badge bg-danger">Club fehlt</span>
                                @endif
                            </td>
                            <td>
                                <small class="text-muted">
                                    {{ $cand->source_file }}
                                </small>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('para-records.import-candidates.edit', $cand) }}"
                                   class="btn btn-sm btn-primary">
                                    Auflösen
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            {{ $candidates->links() }}
        @endif
    </div>
@endsection
