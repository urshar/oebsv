@csrf

<div class="row">
    <div class="col-md-3 mb-3">
        <label for="number" class="form-label">Event-Nr.</label>
        <input type="number"
               name="number"
               id="number"
               class="form-control @error('number') is-invalid @enderror"
               value="{{ old('number', $event->number) }}"
               required>
        @error('number')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="order" class="form-label">Reihenfolge</label>
        <input type="number"
               name="order"
               id="order"
               class="form-control @error('order') is-invalid @enderror"
               value="{{ old('order', $event->order) }}">
        @error('order')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="round" class="form-label">Runde</label>
        <input type="text"
               name="round"
               id="round"
               class="form-control @error('round') is-invalid @enderror"
               value="{{ old('round', $event->round) }}"
               maxlength="10">
        @error('round')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mb-3">
    <label for="swimstyle_id" class="form-label">Schwimmstil</label>
    <select name="swimstyle_id"
            id="swimstyle_id"
            class="form-select @error('swimstyle_id') is-invalid @enderror"
            required>
        <option value="">– bitte wählen –</option>
        @foreach($swimstyles as $style)
            @php
                $label = $style->distance.'m '.$style->stroke.
                         ($style->relaycount > 1 ? ' Staffel ('.$style->relaycount.'x)' : '');
            @endphp
            <option value="{{ $style->id }}"
                {{ (int) old('swimstyle_id', $event->swimstyle_id) === $style->id ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>
    @error('swimstyle_id')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="fee" class="form-label">Startgeld</label>
        <input type="number"
               step="0.01"
               min="0"
               name="fee"
               id="fee"
               class="form-control @error('fee') is-invalid @enderror"
               value="{{ old('fee', $event->fee) }}">
        @error('fee')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="fee_currency" class="form-label">Währung</label>
        <input type="text"
               name="fee_currency"
               id="fee_currency"
               class="form-control @error('fee_currency') is-invalid @enderror"
               value="{{ old('fee_currency', $event->fee_currency ?? 'EUR') }}"
               maxlength="10">
        @error('fee_currency')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<button type="submit" class="btn btn-primary">Speichern</button>
<a href="{{ route('meets.show', $session->para_meet_id) }}" class="btn btn-secondary">
    Abbrechen
</a>
