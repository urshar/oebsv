@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Relay Entry anlegen</h1>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('meets.relay-entries.store', $meet) }}">
            @include('relays.entries._form', ['relayEntry' => null])
            <button class="btn btn-primary">Speichern</button>
            <a class="btn btn-link" href="{{ route('meets.relay-entries.index', $meet) }}">Abbrechen</a>
        </form>
    </div>
@endsection
