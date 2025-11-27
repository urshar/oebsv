@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Kontinent bearbeiten</h1>

        <form action="{{ route('continents.update', $continent) }}" method="POST" class="mt-3">
            @method('PUT')
            @include('continents._form')
        </form>
    </div>
@endsection
