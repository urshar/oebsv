@csrf

<div class="mb-3">
    <label for="code" class="form-label">Code</label>
    <input type="text"
           name="code"
           id="code"
           value="{{ old('code', $continent->code) }}"
           class="form-control @error('code') is-invalid @enderror"
           maxlength="5"
           required>
    @error('code')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="nameEn" class="form-label">Name (EN)</label>
    <input type="text"
           name="nameEn"
           id="nameEn"
           value="{{ old('nameEn', $continent->nameEn) }}"
           class="form-control @error('nameEn') is-invalid @enderror"
           maxlength="20"
           required>
    @error('nameEn')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="nameDe" class="form-label">Name (DE)</label>
    <input type="text"
           name="nameDe"
           id="nameDe"
           value="{{ old('nameDe', $continent->nameDe) }}"
           class="form-control @error('nameDe') is-invalid @enderror"
           maxlength="20">
    @error('nameDe')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<button type="submit" class="btn btn-primary">
    Speichern
</button>

<a href="{{ route('continents.index') }}" class="btn btn-secondary">
    Abbrechen
</a>
