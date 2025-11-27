@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Entries importieren – {{ $meet->name }}</h1>

        @if($errors->has('lenex_file'))
            <div class="alert alert-danger mt-2">
                {{ $errors->first('lenex_file') }}
            </div>
        @endif

        @if(session('status'))
            <div class="alert alert-success mt-2">
                {{ session('status') }}
            </div>
        @endif

        <div class="card mt-3">
            <div class="card-body">
                <form action="{{ route('lenex.entries.store', $meet) }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label for="lenex_file" class="form-label">
                            Entries-LENEX-Datei (.xml, .lef, .lxf)
                        </label>
                        <input type="file"
                               name="lenex_file"
                               id="lenex_file"
                               class="form-control @error('lenex_file') is-invalid @enderror"
                               required>
                        @error('lenex_file')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Entries importieren
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
