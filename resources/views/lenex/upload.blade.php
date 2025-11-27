@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>LENEX Import – Meetingstruktur</h1>

        @if(session('status'))
            <div class="alert alert-success mt-2">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->has('lenex_file'))
            <div class="alert alert-danger mt-2">
                {{ $errors->first('lenex_file') }}
            </div>
        @endif

        <div class="card mt-3">
            <div class="card-body">
                <form action="{{ route('lenex.upload.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label for="lenex_file" class="form-label">
                            LENEX-Datei (.xml, .lef, .lxf)
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
                        Meetingstruktur importieren
                    </button>
                </form>
            </div>
        </div>

        @if(isset($recentMeets) && $recentMeets->isNotEmpty())
            <div class="mt-4">
                <h2>Zuletzt importierte Meetings</h2>
                <table class="table table-striped table-sm mt-2">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Ort</th>
                        <th>Nation</th>
                        <th>Von</th>
                        <th>Bis</th>
                        <th>Erstellt</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($recentMeets as $meet)
                        <tr>
                            <td>{{ $meet->name }}</td>
                            <td>{{ $meet->city }}</td>
                            <td>{{ $meet->nation?->ioc ?? $meet->nation?->nameEn }}</td>
                            <td>{{ optional($meet->from_date)->format('d.m.Y') }}</td>
                            <td>{{ optional($meet->to_date)->format('d.m.Y') }}</td>
                            <td>{{ optional($meet->created_at)->format('d.m.Y H:i') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
