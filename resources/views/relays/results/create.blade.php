@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Relay Result anlegen</h1>

        <div class="mb-3">
            <div><strong>Entry:</strong> #{{ $relayEntry->id }}</div>
            <div><strong>Verein:</strong> {{ $relayEntry->club?->nameDe ?? $relayEntry->club?->shortNameDe ?? '—' }}
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('meets.relay-entries.relay-results.store', [$meet, $relayEntry]) }}">
            @include('relays.results._form', ['relayResult' => $relayResult, 'swimtime' => ''])
            <button class="btn btn-primary">Speichern</button>
            <a class="btn btn-link" href="{{ route('meets.relay-entries.show', [$meet, $relayEntry]) }}">Abbrechen</a>
        </form>
    </div>
@endsection
