@csrf

<div class="mb-3">
    <label for="number" class="form-label">Session-Nummer</label>
    <input type="number"
           name="number"
           id="number"
           class="form-control @error('number') is-invalid @enderror"
           value="{{ old('number', $session->number) }}"
           required>
    @error('number')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="date" class="form-label">Datum</label>
    <input type="date"
           name="date"
           id="date"
           class="form-control @error('date') is-invalid @enderror"
           value="{{ old('date', optional($session->date)->format('Y-m-d')) }}">
    @error('date')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="start_time" class="form-label">Startzeit</label>
        <input type="time"
               name="start_time"
               id="start_time"
               class="form-control @error('start_time') is-invalid @enderror"
               value="{{ old('start_time', $session->start_time) }}">
        @error('start_time')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label for="warmup_from" class="form-label">Einschwimmen von</label>
        <input type="time"
               name="warmup_from"
               id="warmup_from"
               class="form-control @error('warmup_from') is-invalid @enderror"
               value="{{ old('warmup_from', $session->warmup_from) }}">
        @error('warmup_from')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label for="warmup_until" class="form-label">Einschwimmen bis</label>
        <input type="time"
               name="warmup_until"
               id="warmup_until"
               class="form-control @error('warmup_until') is-invalid @enderror"
               value="{{ old('warmup_until', $session->warmup_until) }}">
        @error('warmup_until')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<button type="submit" class="btn btn-primary">Speichern</button>
<a href="{{ route('meets.show', $meet) }}" class="btn btn-secondary">Abbrechen</a>
