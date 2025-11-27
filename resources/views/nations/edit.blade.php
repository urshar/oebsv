@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Nation bearbeiten</h1>

        <form action="{{ route('nations.update', $nation) }}" method="POST" class="mt-3">
            @method('PUT')
            @include('nations._form')
        </form>
    </div>
@endsection
