@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Athlet anlegen</h1>

        <div class="mb-3">
            <a href="{{ route('athletes.index') }}" class="btn btn-sm btn-secondary">
                &laquo; zurück zur Übersicht
            </a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                Bitte prüfen Sie die Eingaben.
            </div>
        @endif

        <form action="{{ route('athletes.store') }}" method="POST">
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
                    <label for="oebsv_license" class="form-label">ÖBSV Lizenz</label>
                    <input type="text"
                           name="oebsv_license"
                           id="oebsv_license"
                           value="{{ old('oebsv_license') }}"
                           class="form-control @error('oebsv_license') is-invalid @enderror">
                    @error('oebsv_license')
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
                                {{ (string) old('nation_id') === (string) $nation->id ? 'selected' : '' }}>
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

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">E-Mail</label>
                    <input type="email"
                           name="email"
                           id="email"
                           value="{{ old('email') }}"
                           class="form-control @error('email') is-invalid @enderror">
                    @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Telefon</label>
                    <input type="text"
                           name="phone"
                           id="phone"
                           value="{{ old('phone') }}"
                           class="form-control @error('phone') is-invalid @enderror">
                    @error('phone')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                Athlet speichern
            </button>
        </form>
    </div>
@endsection
