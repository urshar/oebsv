{{-- resources/views/paraRecords/import.blade.php --}}
@extends('layouts.app')

@section('title', 'Para-Rekorde importieren')

@section('content')
    <div class="container mt-4">
        <h1 class="mb-3">Para-Rekorde importieren</h1>

        <p class="text-muted">
            Lade eine LENEX-Datei (<code>.xml</code>, <code>.lef</code>, <code>.lxf</code> oder <code>.zip</code>) hoch,
            um Para-Rekorde (inkl. History und Splits) zu importieren.
        </p>

        {{-- Erfolgsmeldung --}}
        @if (session('status'))
            <div class="alert alert-success mt-3">
                {{ session('status') }}
            </div>
        @endif

        {{-- Allgemeine Fehler (z.B. aus dem Catch) --}}
        @if ($errors->any())
            <div class="alert alert-danger mt-3">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card mt-3">
            <div class="card-body">
                <form method="POST"
                      action="{{ route('para-records.import.store') }}"
                      enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label for="lenex_file" class="form-label">LENEX-Datei</label>
                        <input
                            type="file"
                            name="lenex_file"
                            id="lenex_file"
                            class="form-control @error('lenex_file') is-invalid @enderror"
                            accept=".xml,.lef,.lxf,.zip"
                            required
                        >

                        @error('lenex_file')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Import starten
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
