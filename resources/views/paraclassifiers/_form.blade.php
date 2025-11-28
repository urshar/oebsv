{{-- resources/views/paraclassifiers/_form.blade.php --}}

@if ($errors->any())
    <div class="alert alert-danger">
        Please check your input.
    </div>
@endif

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="firstName" class="form-label">First name</label>
        <input type="text"
               name="firstName"
               id="firstName"
               value="{{ old('firstName', $classifier->firstName) }}"
               class="form-control @error('firstName') is-invalid @enderror">
        @error('firstName')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="lastName" class="form-label">Last name</label>
        <input type="text"
               name="lastName"
               id="lastName"
               value="{{ old('lastName', $classifier->lastName) }}"
               class="form-control @error('lastName') is-invalid @enderror">
        @error('lastName')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email"
               name="email"
               id="email"
               value="{{ old('email', $classifier->email) }}"
               class="form-control @error('email') is-invalid @enderror">
        @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="phone" class="form-label">Phone</label>
        <input type="text"
               name="phone"
               id="phone"
               value="{{ old('phone', $classifier->phone) }}"
               class="form-control @error('phone') is-invalid @enderror">
        @error('phone')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="type" class="form-label">Type</label>
        <select name="type"
                id="type"
                class="form-select @error('type') is-invalid @enderror">
            <option value="">-- please choose --</option>
            @foreach(['TECH' => 'Technical', 'MED' => 'Medical', 'BOTH' => 'Both'] as $key => $label)
                <option value="{{ $key }}"
                    {{ old('type', $classifier->type) === $key ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('type')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="wps_id" class="form-label">WPS ID</label>
        <input type="text"
               name="wps_id"
               id="wps_id"
               value="{{ old('wps_id', $classifier->wps_id) }}"
               class="form-control @error('wps_id') is-invalid @enderror">
        @error('wps_id')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Nation --}}
    <div class="col-md-4 mb-3">
        <label for="nation_id" class="form-label">Nation</label>
        <select name="nation_id"
                id="nation_id"
                class="form-select @error('nation_id') is-invalid @enderror">
            <option value="">-- please choose --</option>
            @foreach($nations as $id => $label)
                <option value="{{ $id }}"
                    {{ (string) old('nation_id', $classifier->nation_id) === (string) $id ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('nation_id')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

</div>
