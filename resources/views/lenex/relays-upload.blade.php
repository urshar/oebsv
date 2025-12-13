@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>LENEX – Staffeln importieren</h1>

        <div class="mb-3">
            <div><strong>Meeting:</strong> {{ $meet->name }}</div>
            <div><strong>Ort:</strong> {{ $meet->city }}</div>
            <div>
                <strong>Datum:</strong>
                {{ optional($meet->from_date)?->format('d.m.Y') }}
                @if($meet->to_date)
                    – {{ optional($meet->to_date)?->format('d.m.Y') }}
                @endif
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <form method="POST" enctype="multipart/form-data" action="{{ route('meets.lenex.relays.preview', $meet) }}">
            @csrf

            <div class="mb-3">
                <label class="form-label">Lenex-Datei (lef/lxf/zip/xml)</label>
                <input type="file" class="form-control" name="lenex_file" required>
                @error('lenex_file')
                <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
                <div class="form-text">
                    Upload wird nur für den Preview/Import verwendet.
                </div>
            </div>

            <button class="btn btn-primary">Datei hochladen & Preview anzeigen</button>
            <a class="btn btn-link" href="{{ route('meets.show', $meet) }}">Zurück</a>
        </form>
    </div>
@endsection
