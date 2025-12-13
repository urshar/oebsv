@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Relay Result bearbeiten #{{ $relayResult->id }}</h1>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('meets.relay-results.update', [$meet, $relayResult]) }}">
            @method('PUT')
            @include('relays.results._form', ['relayResult' => $relayResult])
            <button class="btn btn-primary">Aktualisieren</button>
            <a class="btn btn-link"
               href="{{ route('meets.relay-entries.show', [$meet, $relayResult->entry]) }}">Zurück</a>
        </form>
    </div>
@endsection
