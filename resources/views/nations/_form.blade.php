@csrf

<div class="mb-3">
    <label for="nameEn" class="form-label">Name (EN)</label>
    <input type="text"
           name="nameEn"
           id="nameEn"
           value="{{ old('nameEn', $nation->nameEn) }}"
           class="form-control @error('nameEn') is-invalid @enderror"
           maxlength="200"
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
           value="{{ old('nameDe', $nation->nameDe) }}"
           class="form-control @error('nameDe') is-invalid @enderror"
           maxlength="200">
    @error('nameDe')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="ioc" class="form-label">IOC</label>
        <input type="text"
               name="ioc"
               id="ioc"
               value="{{ old('ioc', $nation->ioc) }}"
               class="form-control @error('ioc') is-invalid @enderror"
               maxlength="3">
        @error('ioc')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="iso2" class="form-label">ISO2</label>
        <input type="text"
               name="iso2"
               id="iso2"
               value="{{ old('iso2', $nation->iso2) }}"
               class="form-control @error('iso2') is-invalid @enderror"
               maxlength="2">
        @error('iso2')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="iso3" class="form-label">ISO3</label>
        <input type="text"
               name="iso3"
               id="iso3"
               value="{{ old('iso3', $nation->iso3) }}"
               class="form-control @error('iso3') is-invalid @enderror"
               maxlength="3">
        @error('iso3')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mb-3">
    <label for="continent_id" class="form-label">Kontinent</label>
    <select name="continent_id"
            id="continent_id"
            class="form-select @error('continent_id') is-invalid @enderror">
        <option value="">– kein Kontinent –</option>
        @foreach($continents as $id => $name)
            <option value="{{ $id }}"
                {{ (string) old('continent_id', $nation->continent_id) === (string) $id ? 'selected' : '' }}>
                {{ $name }}
            </option>
        @endforeach
    </select>
    @error('continent_id')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<button type="submit" class="btn btn-primary">
    Speichern
</button>

<a href="{{ route('nations.index') }}" class="btn btn-secondary">
    Abbrechen
</a>
