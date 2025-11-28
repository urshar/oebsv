@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Meeting bearbeiten: {{ $meet->name }}</h1>

        <form method="POST" action="{{ route('meets.update', $meet) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label">Name</label>
                <input
                    type="text"
                    name="name"
                    class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $meet->name) }}"
                >
                @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Ort (Stadt)</label>
                <input
                    type="text"
                    name="city"
                    class="form-control @error('city') is-invalid @enderror"
                    value="{{ old('city', $meet->city) }}"
                >
                @error('city')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            {{-- Falls du Nation editierbar machen willst:
            <div class="mb-3">
                <label class="form-label">Nation</label>
                <select
                    name="nation_id"
                    class="form-select @error('nation_id') is-invalid @enderror"
                >
                    <option value="">– bitte wählen –</option>
                    @foreach($nations as $nation)
                        <option value="{{ $nation->id }}" @selected(old('nation_id', $meet->nation_id) == $nation->id)>
                            {{ $nation->code }} – {{ $nation->name }}
                        </option>
                    @endforeach
                </select>
                @error('nation_id')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            --}}

            <div class="row">
                <div class="mb-3 col-md-6">
                    <label class="form-label">Von (from_date)</label>
                    <input
                        type="date"
                        name="from_date"
                        class="form-control @error('from_date') is-invalid @enderror"
                        value="{{ old('from_date', optional($meet->from_date)?->format('Y-m-d')) }}"
                    >
                    @error('from_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3 col-md-6">
                    <label class="form-label">Bis (to_date)</label>
                    <input
                        type="date"
                        name="to_date"
                        class="form-control @error('to_date') is-invalid @enderror"
                        value="{{ old('to_date', optional($meet->to_date)?->format('Y-m-d')) }}"
                    >
                    @error('to_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row">
                <div class="mb-3 col-md-4">
                    <label class="form-label">Meldestart (entry_start_date)</label>
                    <input
                        type="date"
                        name="entry_start_date"
                        class="form-control @error('entry_start_date') is-invalid @enderror"
                        value="{{ old('entry_start_date', optional($meet->entry_start_date)?->format('Y-m-d')) }}"
                    >
                    @error('entry_start_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3 col-md-4">
                    <label class="form-label">Meldeschluss (entry_deadline)</label>
                    <input
                        type="date"
                        name="entry_deadline"
                        class="form-control @error('entry_deadline') is-invalid @enderror"
                        value="{{ old('entry_deadline', optional($meet->entry_deadline)?->format('Y-m-d')) }}"
                    >
                    @error('entry_deadline')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3 col-md-4">
                    <label class="form-label">Abmeldeschluss (withdraw_until)</label>
                    <input
                        type="date"
                        name="withdraw_until"
                        class="form-control @error('withdraw_until') is-invalid @enderror"
                        value="{{ old('withdraw_until', optional($meet->withdraw_until)?->format('Y-m-d')) }}"
                    >
                    @error('withdraw_until')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row">
                <div class="mb-3">
                    <label class="form-label">Nation</label>
                    <select
                        name="nation_id"
                        class="form-select @error('nation_id') is-invalid @enderror"
                    >
                        <option value="">– bitte wählen –</option>

                        @foreach($continents as $continent)
                            @php
                                $continentLabel = $continent->nameDe ?: $continent->nameEn ?: $continent->code;
                            @endphp

                            @if($continent->nations->isNotEmpty())
                                <optgroup label="{{ $continentLabel }}">
                                    @foreach($continent->nations as $nation)
                                        <option
                                            value="{{ $nation->id }}"
                                            @selected(old('nation_id', $meet->nation_id) == $nation->id)
                                        >
                                            {{ $nation->display_name }}
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </select>

                    @error('nation_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

            </div>

            <button type="submit" class="btn btn-primary">
                Speichern
            </button>
            <a href="{{ route('meets.show', $meet) }}" class="btn btn-secondary">
                Abbrechen
            </a>
        </form>
    </div>
@endsection
