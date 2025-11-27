@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Session hinzufügen – {{ $meet->name }}</h1>

        <form action="{{ route('meets.sessions.store', $meet) }}" method="POST" class="mt-3">
            @include('sessions._form')
        </form>
    </div>
@endsection
