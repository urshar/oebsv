{{-- resources/views/athletes/classifications/_form.blade.php --}}

@php
    // vorhandene Zuordnung aus der Pivot-Tabelle (für edit)
    $selectedTech1 = old(
        'tech_classifier1_id',
        optional($classification->classifiers->firstWhere('pivot.role', 'TECH1'))->id ?? null
    );

    $selectedTech2 = old(
        'tech_classifier2_id',
        optional($classification->classifiers->firstWhere('pivot.role', 'TECH2'))->id ?? null
    );

    $selectedMed = old(
        'med_classifier_id',
        optional($classification->classifiers->firstWhere('pivot.role', 'MED'))->id ?? null
    );
@endphp

<div class="row">
    <div class="col-md-3 mb-3">
        <label for="classification_date" class="form-label">Datum</label>
        <input type="date"
               name="classification_date"
               id="classification_date"
               value="{{ old('classification_date', optional($classification->classification_date)->format('Y-m-d')) }}"
               class="form-control @error('classification_date') is-invalid @enderror">
        @error('classification_date')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-5 mb-3">
        <label for="location" class="form-label">Ort</label>
        <input type="text"
               name="location"
               id="location"
               value="{{ old('location', $classification->location) }}"
               class="form-control @error('location') is-invalid @enderror">
        @error('location')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <div class="form-check mt-4">
            <input type="checkbox"
                   name="is_international"
                   id="is_international"
                   value="1"
                   class="form-check-input"
                {{ old('is_international', $classification->is_international) ? 'checked' : '' }}>
            <label for="is_international" class="form-check-label">
                Internationale Klassifikation (WPS)
            </label>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="wps_license" class="form-label">WPS-Lizenz</label>
        <input type="text"
               name="wps_license"
               id="wps_license"
               value="{{ old('wps_license', $classification->wps_license ?? $athlete->license) }}"
               class="form-control @error('wps_license') is-invalid @enderror">
        @error('wps_license')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="sportclass_s" class="form-label">S-Klasse</label>
        <input type="text"
               name="sportclass_s"
               id="sportclass_s"
               value="{{ old('sportclass_s', $classification->sportclass_s) }}"
               class="form-control @error('sportclass_s') is-invalid @enderror">
        @error('sportclass_s')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-3 mb-3">
        <label for="sportclass_sb" class="form-label">SB-Klasse</label>
        <input type="text"
               name="sportclass_sb"
               id="sportclass_sb"
               value="{{ old('sportclass_sb', $classification->sportclass_sb) }}"
               class="form-control @error('sportclass_sb') is-invalid @enderror">
        @error('sportclass_sb')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-2 mb-3">
        <label for="sportclass_sm" class="form-label">SM-Klasse</label>
        <input type="text"
               name="sportclass_sm"
               id="sportclass_sm"
               value="{{ old('sportclass_sm', $classification->sportclass_sm) }}"
               class="form-control @error('sportclass_sm') is-invalid @enderror">
        @error('sportclass_sm')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="sportclass_exception" class="form-label">Ausnahme (z.B. T, B, Review)</label>
        <input type="text"
               name="sportclass_exception"
               id="sportclass_exception"
               value="{{ old('sportclass_exception', $classification->sportclass_exception) }}"
               class="form-control @error('sportclass_exception') is-invalid @enderror">
        @error('sportclass_exception')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="status" class="form-label">Status</label>
        <input type="text"
               name="status"
               id="status"
               value="{{ old('status', $classification->status) }}"
               class="form-control @error('status') is-invalid @enderror"
               placeholder="Confirmed / Review / National ...">
        @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<h5>Panel</h5>
<div class="row">
    {{-- TECH 1 --}}
    <div class="col-md-4 mb-3">
        <label for="tech_classifier1_id" class="form-label">Tech. Klassifizierer 1</label>
        <select
            name="tech_classifier1_id"
            id="tech_classifier1_id"
            class="form-select @error('tech_classifier1_id') is-invalid @enderror"
        >
            <option value="">-- bitte wählen --</option>
            @foreach($technicalClassifiers as $classifier)
                <option value="{{ $classifier->id }}"
                    {{ $selectedTech1 == $classifier->id ? 'selected' : '' }}>
                    {{ $classifier->fullName }}
                    @if($classifier->wps_id)
                        ({{ $classifier->wps_id }})
                    @endif
                </option>
            @endforeach
        </select>
        @error('tech_classifier1_id')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- TECH 2 --}}
    <div class="col-md-4 mb-3">
        <label for="tech_classifier2_id" class="form-label">Tech. Klassifizierer 2</label>
        <select
            name="tech_classifier2_id"
            id="tech_classifier2_id"
            class="form-select @error('tech_classifier2_id') is-invalid @enderror"
        >
            <option value="">-- bitte wählen --</option>
            @foreach($technicalClassifiers as $classifier)
                <option value="{{ $classifier->id }}"
                    {{ $selectedTech2 == $classifier->id ? 'selected' : '' }}>
                    {{ $classifier->fullName }}
                    @if($classifier->wps_id)
                        ({{ $classifier->wps_id }})
                    @endif
                </option>
            @endforeach
        </select>
        @error('tech_classifier2_id')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- MED --}}
    <div class="col-md-4 mb-3">
        <label for="med_classifier_id" class="form-label">Med. Klassifizierer</label>
        <select
            name="med_classifier_id"
            id="med_classifier_id"
            class="form-select @error('med_classifier_id') is-invalid @enderror"
        >
            <option value="">-- bitte wählen --</option>
            @foreach($medicalClassifiers as $classifier)
                <option value="{{ $classifier->id }}"
                    {{ $selectedMed == $classifier->id ? 'selected' : '' }}>
                    {{ $classifier->fullName }}
                    @if($classifier->wps_id)
                        ({{ $classifier->wps_id }})
                    @endif
                </option>
            @endforeach
        </select>
        @error('med_classifier_id')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mb-3">
    <label for="notes" class="form-label">Notizen</label>
    <textarea name="notes"
              id="notes"
              rows="4"
              class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $classification->notes) }}</textarea>
    @error('notes')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
