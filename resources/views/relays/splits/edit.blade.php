@php use App\Support\SwimTime; @endphp
@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Team-Split bearbeiten #{{ $relaySplit->id }}</h1>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('meets.relay-splits.update', [$meet, $relaySplit]) }}">
            @csrf @method('PUT')

            <div class="mb-3">
                <label class="form-label">Distance</label>
                <input class="form-control" value="{{ $relaySplit->distance }}" disabled>
            </div>

            <div class="mb-3">
                <label class="form-label">Cumulative time</label>
                <input class="form-control" name="cumulative_time"
                       value="{{ old('cumulative_time', SwimTime::format($relaySplit->cumulative_time_ms)) }}"
                       required>
                @error('cumulative_time')
                <div class="text-danger mt-1">{{ $message }}</div> @enderror
            </div>

            <button class="btn btn-primary">Aktualisieren</button>
            <a class="btn btn-link"
               href="{{ route('meets.relay-results.relay-splits.index', [$meet, $relaySplit->result]) }}">Zurück</a>
        </form>
    </div>
@endsection
