@php
    use App\Support\SwimTime;
@endphp

@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Leg-Splits – Member #{{ $relayMember->id }}</h1>

        <div class="mb-3">
            <a class="btn btn-link" href="{{ route('meets.relay-entries.show', [$meet, $relayMember->entry]) }}">Zurück
                zur Entry</a>
        </div>

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach</ul>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <div><strong>Leg:</strong> {{ $relayMember->leg }}</div>
                <div>
                    <strong>Athlet:</strong>
                    @if($relayMember->athlete)
                        {{ $relayMember->athlete->lastName }}, {{ $relayMember->athlete->firstName }}
                        (#{{ $relayMember->athlete->id }})
                    @else
                        —
                    @endif
                </div>
                <div><strong>Leg Distance:</strong> {{ $relayMember->leg_distance ?? '—' }}</div>
            </div>
        </div>

        <h4>Neuen Leg-Split hinzufügen</h4>
        <form class="row g-2 mb-4" method="POST"
              action="{{ route('meets.relay-members.relay-leg-splits.store', [$meet, $relayMember]) }}">
            @csrf
            <div class="col-md-3">
                <input class="form-control" type="number" name="distance_in_leg" min="1"
                       placeholder="Distance in leg (z.B. 25)" required>
            </div>
            <div class="col-md-4">
                <input class="form-control" name="cumulative_time" placeholder="Cumulative (z.B. 00:32.15)" required
                       value="{{ old('cumulative_time') }}">
                @error('cumulative_time')
                <div class="text-danger mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <input class="form-control" type="number" name="absolute_distance" min="1"
                       placeholder="Absolute distance (optional)">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary">Speichern</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                <tr>
                    <th>Distance in leg</th>
                    <th>Cumulative (ms)</th>
                    <th>Split (ms)</th>
                    <th>Absolute distance</th>
                    <th style="width:200px;"></th>
                </tr>
                </thead>
                <tbody>
                @forelse($splits as $s)
                    <tr>
                        <td>{{ $s->distance_in_leg }}</td>
                        <td>{{ SwimTime::format($s->cumulative_time_ms) }}</td>
                        <td>{{ $s->split_time_ms !== null ? SwimTime::format($s->split_time_ms) : '—' }}</td>
                        <td>{{ $s->absolute_distance ?? '—' }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary"
                               href="{{ route('meets.relay-leg-splits.edit', [$meet, $s]) }}">Edit</a>
                            <form class="d-inline" method="POST"
                                  action="{{ route('meets.relay-leg-splits.destroy', [$meet, $s]) }}"
                                  onsubmit="return confirm('Leg-Split löschen?');">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Löschen</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-muted">Keine Leg-Splits vorhanden.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
