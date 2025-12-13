@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>LENEX-Resultate importieren – {{ $meet->name }}</h1>

        <p>
            Ort: {{ $meet->city }}<br>
            Datum:
            {{ optional($meet->from_date)?->format('d.m.Y') }}
            @if($meet->to_date)
                – {{ optional($meet->to_date)?->format('d.m.Y') }}
            @endif
        </p>

        {{-- Fehlermeldungen anzeigen --}}
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Statusmeldung (z.B. nach erfolgreichem Import) --}}
        @if(session('status'))
            <div class="alert alert-success">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST"
              action="{{ route('meets.lenex.results.preview', $meet) }}"
              enctype="multipart/form-data">
            @csrf

            <div class="mb-3">
                <label for="lenex_file" class="form-label">Lenex-Resultdatei (xml/lef/lxf/zip)</label>
                <input type="file"
                       name="lenex_file"
                       id="lenex_file"
                       class="form-control"
                       required>

                @error('lenex_file')
                <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary">
                Datei hochladen und Schwimmer auswählen
            </button>

            <a href="{{ route('meets.show', $meet) }}" class="btn btn-secondary">
                Zurück zum Meeting
            </a>
        </form>
    </div>
@endsection
