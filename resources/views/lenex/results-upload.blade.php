@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Resultate importieren – {{ $meet->name }}</h1>

        <p>
            Veranstaltung: <strong>{{ $meet->name }}</strong><br>
            Ort: {{ $meet->city }}<br>
            Datum: {{ optional($meet->from_date)?->format('d.m.Y') }}
            @if($meet->to_date)
                – {{ optional($meet->to_date)?->format('d.m.Y') }}
            @endif
        </p>

        @if(session('status'))
            <div class="alert alert-success">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST"
              action="{{ route('lenex.results.store', $meet) }}"
              enctype="multipart/form-data">
            @csrf

            <div class="mb-3">
                <input type="file"
                       name="lenex_file"
                       class="form-control @error('lenex_file') is-invalid @enderror"
                       required>

                @error('lenex_file')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary">
                Resultate importieren
            </button>

            <a href="{{ route('meets.show', $meet) }}" class="btn btn-secondary">
                Abbrechen
            </a>
        </form>
    </div>
@endsection
