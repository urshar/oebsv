@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Session bearbeiten – {{ $meet->name }}</h1>

        <form action="{{ route('sessions.update', $session) }}" method="POST" class="mt-3">
            @method('PUT')
            @include('sessions._form')
        </form>
    </div>
@endsection
