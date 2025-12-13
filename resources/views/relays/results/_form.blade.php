@php use App\Support\SwimTime; @endphp
@csrf

<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Swimtime</label>
        <input
            class="form-control"
            name="swimtime"
            value="{{ old('swimtime', $swimtime ?? SwimTime::format($relayResult->time_ms ?? null)) }}"
            placeholder="z.B. 01:05.32"
        >
        @error('swimtime')
        <div class="text-danger mt-1">{{ $message }}</div> @enderror
        <div class="form-text">Format z.B. 00:32.15 oder 01:05.32 oder 00:02:10.35</div>
    </div>

    <div class="col-md-2 mb-3">
        <label class="form-label">Rang</label>
        <input class="form-control" type="number" name="rank" value="{{ old('rank', $relayResult->rank ?? '') }}">
        @error('rank')
        <div class="text-danger mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2 mb-3">
        <label class="form-label">Heat</label>
        <input class="form-control" type="number" name="heat" value="{{ old('heat', $relayResult->heat ?? '') }}">
        @error('heat')
        <div class="text-danger mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2 mb-3">
        <label class="form-label">Lane</label>
        <input class="form-control" type="number" name="lane" value="{{ old('lane', $relayResult->lane ?? '') }}">
        @error('lane')
        <div class="text-danger mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-2 mb-3">
        <label class="form-label">Points</label>
        <input class="form-control" type="number" name="points" value="{{ old('points', $relayResult->points ?? '') }}">
        @error('points')
        <div class="text-danger mt-1">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <input class="form-control" name="status" value="{{ old('status', $relayResult->status ?? 'OK') }}">
    @error('status')
    <div class="text-danger mt-1">{{ $message }}</div> @enderror
</div>
