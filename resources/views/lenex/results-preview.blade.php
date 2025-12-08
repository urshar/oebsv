@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Ergebnisse importieren – Auswahl Schwimmer</h1>

        <p>
            Veranstaltung: <strong>{{ $meet->name }}</strong><br>
            Ort: {{ $meet->city }}<br>
            Datum:
            {{ optional($meet->from_date)?->format('d.m.Y') }}
            @if($meet->to_date)
                – {{ optional($meet->to_date)?->format('d.m.Y') }}
            @endif
        </p>

        {{-- WICHTIG: jetzt zur neuen Route passen --}}
        <form method="POST" action="{{ route('meets.lenex.results.import', $meet) }}">
            @csrf

            <input type="hidden" name="lenex_file_path" value="{{ $lenexFilePath }}">

            <div class="mb-2">
                <label class="form-check-label">
                    <input type="checkbox" id="select-all" class="form-check-input" checked>
                    Alle Nationen / Vereine / Schwimmer auswählen / abwählen
                </label>
            </div>

            @php
                $nations = collect($clubs)->groupBy('nation');
            @endphp

            <div class="border rounded p-2" style="max-height: 60vh; overflow-y: auto;">
                @forelse($nations as $nationCode => $nationClubs)
                    @php
                        $nationId = 'nation-' . ($nationCode ?: 'UNKNOWN');
                    @endphp

                    <div class="mb-2">
                        {{-- Nation-Ebene --}}
                        <div class="form-check fw-bold">
                            <input type="checkbox"
                                   class="form-check-input nation-checkbox"
                                   id="{{ $nationId }}"
                                   data-nation="{{ $nationCode }}"
                                   checked>
                            <label class="form-check-label" for="{{ $nationId }}">
                                {{ $nationCode ?: 'ohne Nation' }}
                            </label>
                        </div>

                        {{-- Vereine dieser Nation --}}
                        <div class="ms-3">
                            @foreach($nationClubs as $club)
                                @php
                                    $clubKey    = ($club['club_id'] ?: md5($club['club_name'] . $nationCode));
                                    $clubIdAttr = 'club-' . $nationCode . '-' . $clubKey;
                                @endphp

                                <div class="mb-1">
                                    <div class="form-check">
                                        <input type="checkbox"
                                               class="form-check-input club-checkbox"
                                               id="{{ $clubIdAttr }}"
                                               data-nation="{{ $nationCode }}"
                                               data-club="{{ $clubKey }}"
                                               checked>
                                        <label class="form-check-label fw-semibold" for="{{ $clubIdAttr }}">
                                            {{ $club['club_name'] ?: 'ohne Vereinsname' }}
                                        </label>
                                    </div>

                                    {{-- Schwimmer dieses Vereins --}}
                                    <ul class="list-unstyled ms-4 mb-1">
                                        @foreach($club['athletes'] as $athlete)
                                            @php
                                                $aid           = $athlete['lenex_athlete_id'];
                                                $athleteIdAttr = 'athlete-' . $aid;
                                            @endphp
                                            <li>
                                                <div class="form-check">
                                                    <input type="checkbox"
                                                           class="form-check-input athlete-checkbox"
                                                           id="{{ $athleteIdAttr }}"
                                                           name="selected_athletes[]"
                                                           value="{{ $aid }}"
                                                           data-nation="{{ $nationCode }}"
                                                           data-club="{{ $clubKey }}"
                                                           checked>
                                                    <label class="form-check-label" for="{{ $athleteIdAttr }}">
                                                        {{ $athlete['last_name'] }}, {{ $athlete['first_name'] }}
                                                        @if($athlete['license'])
                                                            <span class="text-muted">({{ $athlete['license'] }})</span>
                                                        @endif
                                                    </label>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <hr>
                @empty
                    <div class="alert alert-warning">
                        In dieser Datei wurden keine Athleten mit Ergebnissen gefunden.
                    </div>
                @endforelse
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    Weiter zur Bestätigung
                </button>

                <a href="{{ route('meets.show', $meet) }}" class="btn btn-secondary">
                    Abbrechen
                </a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAll = document.getElementById('select-all');
            const nationCheckboxes = document.querySelectorAll('.nation-checkbox');
            const clubCheckboxes = document.querySelectorAll('.club-checkbox');
            const athleteCheckboxes = document.querySelectorAll('.athlete-checkbox');

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    const checked = selectAll.checked;
                    nationCheckboxes.forEach(cb => cb.checked = checked);
                    clubCheckboxes.forEach(cb => cb.checked = checked);
                    athleteCheckboxes.forEach(cb => cb.checked = checked);
                });
            }

            nationCheckboxes.forEach(function (nationCb) {
                nationCb.addEventListener('change', function () {
                    const nation = nationCb.getAttribute('data-nation');
                    const checked = nationCb.checked;

                    document.querySelectorAll('.club-checkbox[data-nation="' + nation + '"]')
                        .forEach(cb => cb.checked = checked);

                    document.querySelectorAll('.athlete-checkbox[data-nation="' + nation + '"]')
                        .forEach(cb => cb.checked = checked);
                });
            });

            clubCheckboxes.forEach(function (clubCb) {
                clubCb.addEventListener('change', function () {
                    const nation = clubCb.getAttribute('data-nation');
                    const club = clubCb.getAttribute('data-club');
                    const checked = clubCb.checked;

                    document.querySelectorAll(
                        '.athlete-checkbox[data-nation="' + nation + '"][data-club="' + club + '"]'
                    ).forEach(cb => cb.checked = checked);
                });
            });
        });
    </script>
@endpush
