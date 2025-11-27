@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Kontinent anlegen</h1>

        <form action="{{ route('continents.store') }}" method="POST" class="mt-3">
            @include('continents._form')
        </form>
    </div>
@endsection

