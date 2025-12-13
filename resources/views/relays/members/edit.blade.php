@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Relay Member bearbeiten #{{ $relayMember->id }}</h1>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('meets.relay-members.update', [$meet, $relayMember]) }}">
            @method('PUT')
            @include('relays.members._form', ['relayMember' => $relayMember, 'isCreate' => false])
            <button class="btn btn-primary">Aktualisieren</button>
            <a class="btn btn-link"
               href="{{ route('meets.relay-entries.show', [$meet, $relayMember->entry]) }}">Zurück</a>
        </form>
    </div>
@endsection
