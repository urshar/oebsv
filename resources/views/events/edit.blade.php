@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Event bearbeiten – Session {{ $session->number }} ({{ optional($session->date)->format('d.m.Y') }})</h1>

        <form action="{{ route('events.update', $event) }}" method="POST" class="mt-3">
            @method('PUT')
            @include('events._form')
        </form>
    </div>
@endsection
