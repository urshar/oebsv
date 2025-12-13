@php
    use App\Support\SwimTime;
@endphp

@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Leg-Split bearbeiten #{{ $relayLegSplit->id }}</h1>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('meets.relay-leg-splits.update', [$meet, $relayLegSplit]) }}">
            @csrf @method('PUT')

            <div class="mb-3">
                <label class="form-label">Distance in leg</label>
                <input class="form-control" value="{{ $relayLegSplit->distance_in_leg }}" disabled>
            </div>

            <div class="mb-3">
                <label class="form-label">Cumulative time (ms)</label>
                <input class="form-control" name="cumulative_time"
                       value="{{ old('cumulative_time', SwimTime::format($relayLegSplit->cumulative_time_ms)) }}"
                       required>
                @error('cumulative_time_ms')
                <div class="text-danger mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Absolute distance (optional)</label>
                <input class="form-control" type="number" name="absolute_distance" min="1"
                       value="{{ old('absolute_distance', $relayLegSplit->absolute_distance) }}">
                @error('absolute_distance')
                <div class="text-danger mt-1">{{ $message }}</div> @enderror
            </div>

            <button class="btn btn-primary">Aktualisieren</button>
            <a class="btn btn-link"
               href="{{ route('meets.relay-members.relay-leg-splits.index', [$meet, $relayLegSplit->member]) }}">Zurück</a>
        </form>
    </div>
@endsection
