@extends('layouts.app')

@section('title', 'Kandidat auflösen')

@section('content')
    <div class="container mt-4">
        <h1 class="mb-3">Kandidat #{{ $candidate->id }} auflösen</h1>

        <a href="{{ route('para-records.import-candidates.index') }}" class="btn btn-sm btn-secondary mb-3">
            &larr; Zurück zur Übersicht
        </a>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Basisdaten --}}
        <div class="card mb-3">
            <div class="card-header">
                Rekord-Information
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Athlete</dt>
                    <dd class="col-sm-9">
                        {{ $candidate->athlete_lastname }}, {{ $candidate->athlete_firstname }}
                        @if($candidate->athlete_birthdate)
                            ({{ \Illuminate\Support\Carbon::parse($candidate->athlete_birthdate)->format('d.m.Y') }})
                        @endif
                        @if($candidate->athlete_license)
                            <br><small class="text-muted">Lizenz: {{ $candidate->athlete_license }}</small>
                        @endif
                    </dd>

                    <dt class="col-sm-3">Club</dt>
                    <dd class="col-sm-9">
                        {{ $candidate->club_name }}
                        @if($candidate->club_code)
                            <small class="text-muted">({{ $candidate->club_code }})</small>
                        @endif
                    </dd>

                    <dt class="col-sm-3">Event</dt>
                    <dd class="col-sm-9">
                        {{ $candidate->distance }}m {{ $candidate->stroke }} –
                        {{ $candidate->course }}, {{ $candidate->sport_class }},
                        Kategorie: {{ $candidate->agegroup_code }}
                    </dd>

                    <dt class="col-sm-3">Zeit</dt>
                    <dd class="col-sm-9">
                        {{ number_format($candidate->swimtime_ms / 1000, 2) }} s
                        @if($candidate->swum_at)
                            am {{ \Illuminate\Support\Carbon::parse($candidate->swum_at)->format('d.m.Y') }}
                        @endif
                    </dd>

                    <dt class="col-sm-3">Quelle</dt>
                    <dd class="col-sm-9">
                        {{ $candidate->source_file }}
                    </dd>
                </dl>
            </div>
        </div>

        <form method="POST" action="{{ route('para-records.import-candidates.update', $candidate) }}">
            @csrf

            <div class="row">
                {{-- ATHLETE --}}
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header">
                            Athlete zuordnen
                        </div>
                        <div class="card-body">
                            <p>
                                <strong>Kandidat:</strong><br>
                                {{ $candidate->athlete_lastname }}, {{ $candidate->athlete_firstname }}
                                @if($candidate->athlete_birthdate)
                                    ({{ \Illuminate\Support\Carbon::parse($candidate->athlete_birthdate)->format('d.m.Y') }})
                                @endif
                            </p>

                            @if($matchingAthletes->isEmpty())
                                <p class="text-muted">Keine passenden Athleten gefunden.</p>
                            @else
                                <div class="mb-2">
                                    <label class="form-label">Bestehenden Athleten auswählen:</label>
                                    <select name="para_athlete_id" class="form-select form-select-sm">
                                        <option value="">– keiner –</option>
                                        @foreach($matchingAthletes as $ath)
                                            <option value="{{ $ath->id }}">
                                                #{{ $ath->id }} –
                                                {{ $ath->lastName }}, {{ $ath->firstName }}
                                                @if($ath->birthdate)
                                                    ({{ \Illuminate\Support\Carbon::parse($ath->birthdate)->format('d.m.Y') }})
                                                @endif
                                                @if($ath->club)
                                                    – {{ $ath->club->nameDe }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="create_new_athlete"
                                       name="create_new_athlete">
                                <label class="form-check-label" for="create_new_athlete">
                                    Neuen Athleten aus obigen Daten anlegen
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- CLUB --}}
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-header">
                            Club zuordnen
                        </div>
                        <div class="card-body">
                            <p>
                                <strong>Kandidat:</strong><br>
                                {{ $candidate->club_name }}
                                @if($candidate->club_code)
                                    ({{ $candidate->club_code }})
                                @endif
                            </p>

                            @if($matchingClubs->isEmpty())
                                <p class="text-muted">Keine passenden Clubs gefunden.</p>
                            @else
                                <div class="mb-2">
                                    <label class="form-label">Bestehenden Club auswählen:</label>
                                    <select name="para_club_id" class="form-select form-select-sm">
                                        <option value="">– keiner –</option>
                                        @foreach($matchingClubs as $club)
                                            <option value="{{ $club->id }}">
                                                #{{ $club->id }} – {{ $club->nameDe }}
                                                @if($club->clubCode)
                                                    ({{ $club->clubCode }})
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="create_new_club"
                                       name="create_new_club">
                                <label class="form-check-label" for="create_new_club">
                                    Neuen Club aus obigen Daten anlegen
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button class="btn btn-primary">
                Rekord anlegen & Kandidat auflösen
            </button>
        </form>
    </div>
@endsection
