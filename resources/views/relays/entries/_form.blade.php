@csrf

<div class="mb-3">
    <label class="form-label">Relay Event</label>
    <select class="form-select" name="para_event_id" required>
        <option value="">Bitte wählen…</option>
        @foreach($relayEvents as $ev)
            <option value="{{ $ev->id }}"
                @selected(old('para_event_id', $relayEntry->para_event_id ?? null) == $ev->id)>
                {{ $ev->number ?? 'Event' }} – {{ $ev->swimstyle?->distance }}m {{ $ev->swimstyle?->stroke }}
            </option>
        @endforeach
    </select>
    @error('para_event_id')
    <div class="text-danger mt-1">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label">Verein</label>
    <select class="form-select" name="para_club_id" required>
        <option value="">Bitte wählen…</option>
        @foreach($clubs as $c)
            <option value="{{ $c->id }}"
                @selected(old('para_club_id', $relayEntry->para_club_id ?? null) == $c->id)>
                {{ $c->nameDe ?? $c->shortNameDe ?? ('Club #'.$c->id) }}
            </option>
        @endforeach
    </select>
    @error('para_club_id')
    <div class="text-danger mt-1">{{ $message }}</div> @enderror
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Relay Nummer (LENEX)</label>
        <input class="form-control" name="lenex_relay_number"
               value="{{ old('lenex_relay_number', $relayEntry->lenex_relay_number ?? '') }}">
        @error('lenex_relay_number')
        <div class="text-danger mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Gender</label>
        <input class="form-control" name="gender" value="{{ old('gender', $relayEntry->gender ?? '') }}">
        @error('gender')
        <div class="text-danger mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Entry Time (LENEX String)</label>
        <input class="form-control" name="entry_time" value="{{ old('entry_time', $relayEntry->entry_time ?? '') }}"
               placeholder="z.B. 00:02:10.35">
        @error('entry_time')
        <div class="text-danger mt-1">{{ $message }}</div> @enderror
    </div>
</div>
