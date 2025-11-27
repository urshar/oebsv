@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Neuen Athleten anlegen – {{ $meet->name }}</h1>

        <p class="text-muted">
            Ort: {{ $meet->city }},
            Nation: {{ $meet->nation?->ioc ?? $meet->nation?->nameEn }}
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

        <form action="{{ route('meets.athletes.store', $meet) }}" method="POST" class="mt-3">
            @csrf

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="firstName" class="form-label">Vorname</label>
                    <input type="text"
                           name="firstName"
                           id="firstName"
                           value="{{ old('firstName') }}"
                           class="form-control @error('firstName') is-invalid @enderror"
                           required>
                    @error('firstName')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label for="lastName" class="form-label">Nachname</label>
                    <input type="text"
                           name="lastName"
                           id="lastName"
                           value="{{ old('lastName') }}"
                           class="form-control @error('lastName') is-invalid @enderror"
                           required>
                    @error('lastName')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="gender" class="form-label">Geschlecht</label>
                    <select name="gender"
                            id="gender"
                            class="form-select @error('gender') is-invalid @enderror">
                        <option value="">– bitte wählen –</option>
                        <option value="M" {{ old('gender') === 'M' ? 'selected' : '' }}>Männlich</option>
                        <option value="F" {{ old('gender') === 'F' ? 'selected' : '' }}>Weiblich</option>
                        <option value="X" {{ old('gender') === 'X' ? 'selected' : '' }}>X</option>
                    </select>
                    @error('gender')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3 mb-3">
                    <label for="birthdate" class="form-label">Geburtsdatum</label>
                    <input type="date"
                           name="birthdate"
                           id="birthdate"
                           value="{{ old('birthdate') }}"
                           class="form-control @error('birthdate') is-invalid @enderror">
                    @error('birthdate')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3 mb-3">
                    <label for="license" class="form-label">Lizenz (optional)</label>
                    <input type="text"
                           name="license"
                           id="license"
                           value="{{ old('license') }}"
                           class="form-control @error('license') is-invalid @enderror">
                    @error('license')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="nation_id" class="form-label">Nation</label>
                    <select name="nation_id"
                            id="nation_id"
                            class="form-select @error('nation_id') is-invalid @enderror">
                        <option value="">– wählen –</option>
                        @foreach($nations as $nation)
                            <option value="{{ $nation->id }}"
                                {{ (string) old('nation_id', $meet->nation_id) === (string) $nation->id ? 'selected' : '' }}>
                                {{ $nation->ioc }} – {{ $nation->nameEn }}
                            </option>
                        @endforeach
                    </select>
                    @error('nation_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-8 mb-3">
                    <label for="para_club_id" class="form-label">Verein</label>
                    <select name="para_club_id"
                            id="para_club_id"
                            class="form-select @error('para_club_id') is-invalid @enderror">
                        <option value="">– kein Verein –</option>
                        @foreach($clubs as $club)
                            <option value="{{ $club->id }}"
                                {{ (string) old('para_club_id') === (string) $club->id ? 'selected' : '' }}>
                                {{ $club->nameDe ?? $club->nameEn }}
                                @if($club->nation)
                                    ({{ $club->nation->ioc }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('para_club_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                Speichern und Events auswählen
            </button>
        </form>
    </div>
@endsection
