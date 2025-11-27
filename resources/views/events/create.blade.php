@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Event hinzufügen – Session {{ $session->number }} ({{ optional($session->date)->format('d.m.Y') }})</h1>

        <form action="{{ route('sessions.events.store', $session) }}" method="POST" class="mt-3">
            @include('events._form')
        </form>
    </div>
@endsection
