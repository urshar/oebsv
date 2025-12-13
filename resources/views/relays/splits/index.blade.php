@php
    use App\Support\SwimTime;
@endphp

@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Team-Splits – Result #{{ $relayResult->id }}</h1>

        <div class="mb-3">
            <a class="btn btn-link" href="{{ route('meets.relay-entries.show', [$meet, $relayResult->entry]) }}">Zurück
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

        <h4>Neuen Split hinzufügen</h4>
        <form class="row g-2 mb-4" method="POST"
              action="{{ route('meets.relay-results.relay-splits.store', [$meet, $relayResult]) }}">
            @csrf
            <div class="col-md-3">
                <input class="form-control" type="number" name="distance" min="1" placeholder="Distance (z.B. 50)"
                       required>
            </div>
            <div class="col-md-4">
                <input class="form-control" name="cumulative_time" placeholder="Cumulative (z.B. 01:05.32)" required
                       value="{{ old('cumulative_time') }}">
                @error('cumulative_time')
                <div class="text-danger mt-1">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary">Speichern</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                <tr>
                    <th>Distance</th>
                    <th>Cumulative (ms)</th>
                    <th>Split (ms)</th>
                    <th style="width:200px;"></th>
                </tr>
                </thead>
                <tbody>
                @forelse($splits as $s)
                    <tr>
                        <td>{{ $s->distance }}</td>
                        <td>{{ SwimTime::format($s->cumulative_time_ms) }}</td>
                        <td>{{ $s->split_time_ms !== null ? SwimTime::format($s->split_time_ms) : '—' }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary"
                               href="{{ route('meets.relay-splits.edit', [$meet, $s]) }}">Edit</a>
                            <form class="d-inline" method="POST"
                                  action="{{ route('meets.relay-splits.destroy', [$meet, $s]) }}"
                                  onsubmit="return confirm('Split löschen?');">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Löschen</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-muted">Keine Splits vorhanden.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
