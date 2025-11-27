@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Nation anlegen</h1>

        <form action="{{ route('nations.store') }}" method="POST" class="mt-3">
            @include('nations._form')
        </form>
    </div>
@endsection
