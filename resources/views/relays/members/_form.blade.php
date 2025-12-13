@php use App\Support\SwimTime; @endphp
@csrf

@if(isset($isCreate) && $isCreate)
    <div class="mb-3">
        <label class="form-label">Leg</label>
        <input class="form-control" type="number" name="leg" min="1" max="20"
               value="{{ old('leg', $relayMember->leg ?? '') }}" required>
        @error('leg')
        <div class="text-danger mt-1">{{ $message }}</div> @enderror
    </div>
@endif

<div class="mb-3">
    <label class="form-label">Athlet</label>
    <select class="form-select" name="para_athlete_id" required>
        <option value="">Bitte wählen…</option>
        @foreach($athletes as $a)
            <option
                value="{{ $a->id }}" @selected(old('para_athlete_id', $relayMember->para_athlete_id ?? null) == $a->id)>
                {{ $a->lastName }}, {{ $a->firstName }} (#{{ $a->id }})
            </option>
        @endforeach
    </select>
    @error('para_athlete_id')
    <div class="text-danger mt-1">{{ $message }}</div> @enderror
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Leg Distance</label>
        <input class="form-control" type="number" name="leg_distance" min="1"
               value="{{ old('leg_distance', $relayMember->leg_distance ?? '') }}">
        @error('leg_distance')
        <div class="text-danger mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Leg Stroke</label>
        <input class="form-control" name="leg_stroke"
               value="{{ old('leg_stroke', $relayMember->leg_stroke ?? '') }}"
               placeholder="z.B. FREE/BACK/...">
        @error('leg_stroke')
        <div class="text-danger mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Leg Time</label>
        <input class="form-control" name="leg_time"
               value="{{ old('leg_time', SwimTime::format($relayMember->leg_time_ms ?? null)) }}"
               placeholder="z.B. 00:32.15">
        @error('leg_time')
        <div class="text-danger mt-1">{{ $message }}</div> @enderror
    </div>
</div>
