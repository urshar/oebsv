@csrf

<div class="mb-3">
    <label for="name" class="form-label">Name der Agegroup</label>
    <input type="text"
           name="name"
           id="name"
           class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $agegroup->name) }}"
           maxlength="100"
           required>
    @error('name')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="gender" class="form-label">Geschlecht</label>
        <select name="gender"
                id="gender"
                class="form-select @error('gender') is-invalid @enderror">
            @php $g = old('gender', $agegroup->gender); @endphp
            <option value="">– alle / mixed –</option>
            <option value="M" {{ $g === 'M' ? 'selected' : '' }}>Männlich</option>
            <option value="F" {{ $g === 'F' ? 'selected' : '' }}>Weiblich</option>
            <option value="X" {{ $g === 'X' ? 'selected' : '' }}>Offen / X</option>
        </select>
        @error('gender')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="age_min" class="form-label">Mindestalter</label>
        <input type="number"
               name="age_min"
               id="age_min"
               class="form-control @error('age_min') is-invalid @enderror"
               value="{{ old('age_min', $agegroup->age_min) }}">
        @error('age_min')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="age_max" class="form-label">Höchstalter</label>
        <input type="number"
               name="age_max"
               id="age_max"
               class="form-control @error('age_max') is-invalid @enderror"
               value="{{ old('age_max', $agegroup->age_max) }}">
        @error('age_max')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mb-3">
    <label for="handicap_raw" class="form-label">Handicap-Liste</label>
    <input type="text"
           name="handicap_raw"
           id="handicap_raw"
           class="form-control @error('handicap_raw') is-invalid @enderror"
           value="{{ old('handicap_raw', $agegroup->handicap_raw) }}"
           placeholder="z.B. 1,2,3,4,5,6,7,8,9,10">
    @error('handicap_raw')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<button type="submit" class="btn btn-primary">Speichern</button>
<a href="{{ route('meets.show', $event->session->para_meet_id) }}" class="btn btn-secondary">
    Abbrechen
</a>
