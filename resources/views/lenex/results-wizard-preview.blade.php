@extends('layouts.app')

@section('content')
    @php
        $resultsClubs = $doResults ? ($resultsData['clubs'] ?? []) : [];
        $relaysClubs  = $doRelays  ? ($relaysData['clubs'] ?? [])  : [];

        // helper für stabile club keys
        $clubKeyFn = function ($nation, $clubName) {
            return md5(((string)($nation ?? '')) . '|' . ((string)($clubName ?? '')));
        };

        // Tree: Results -> Nation -> Club -> Athletes
        $resultsTree = collect($resultsClubs)
            ->groupBy('nation')
            ->map(function($clubs, $nation) use ($clubKeyFn) {
                return collect($clubs)->map(function($club) use ($nation, $clubKeyFn) {
                    $clubName = $club['club_name'] ?? '';
                    $clubKey  = $clubKeyFn($nation, $clubName);

                    $rows = collect($club['rows'] ?? []);

                    $athletes = $rows
                        ->groupBy('lenex_athlete_id')
                        ->map(function($aRows, $athId) {
                            $r = $aRows->first();
                            $name = trim(($r['last_name'] ?? '').', '.($r['first_name'] ?? ''), ', ');
                            return [
                                'athlete_id' => (string)$athId,
                                'name' => $name !== '' ? $name : ('Athlete '.$athId),
                                'count' => $aRows->count(),
                            ];
                        })
                        ->values();

                    return [
                        'club_name' => $clubName,
                        'club_key'  => $clubKey,
                        'athletes'  => $athletes,
                    ];
                })->values();
            });

        // Tree: Relays -> Nation -> Club -> Relay rows
        $relaysTree = collect($relaysClubs)
            ->groupBy('nation')
            ->map(function($clubs, $nation) use ($clubKeyFn) {
                return collect($clubs)->map(function($club) use ($nation, $clubKeyFn) {
                    $clubName = $club['club_name'] ?? '';
                    $clubKey  = $clubKeyFn($nation, $clubName);

                    $relays = collect($club['relay_rows'] ?? [])->map(function($row) {
                        $key = $row['lenex_resultid'] ?: $row['result_id'];
                        $label = trim(($row['relay_event_label'] ?? '—').' · #'.($row['relay_number'] ?? '—'));
                        if (!empty($row['relay_sportclass'])) $label .= ' · '.$row['relay_sportclass'];
                        return [
                            'key' => (string)$key,
                            'label' => $label,
                            'invalid' => (bool)($row['invalid'] ?? false),
                        ];
                    })->values();

                    return [
                        'club_name' => $clubName,
                        'club_key'  => $clubKey,
                        'relays'    => $relays,
                    ];
                })->values();
            });
    @endphp

    <div class="container">
        <h1>LENEX Import Wizard – Preview</h1>

        <div class="mb-2">
            <div><strong>Meet:</strong> {{ $meet->name }}</div>
            <div class="text-muted small">{{ $meet->city ?? '' }}</div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('meets.lenex.results-wizard.import', $meet) }}">
            @csrf
            <input type="hidden" name="lenex_file_path" value="{{ $lenexFilePath }}">

            <div class="row g-3">
                {{-- LEFT: Selection tree --}}
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <strong>Auswahl für Import</strong>
                        </div>
                        <div class="card-body">

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="showSelectedOnly">
                                <label class="form-check-label" for="showSelectedOnly">nur ausgewählte anzeigen</label>
                            </div>

                            @if($doResults)
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong>Athleten</strong>
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                id="toggleAllResults">Alle gültigen
                                        </button>
                                    </div>

                                    <div class="mt-2">
                                        @foreach($resultsTree as $nation => $clubs)
                                            <details class="mb-2" open>
                                                <summary class="d-flex align-items-center gap-2">
                                                    <input type="checkbox" class="form-check-input tree-results-nation"
                                                           data-nation="{{ $nation ?? '' }}">
                                                    <span>{{ $nation ?: '—' }}</span>
                                                </summary>

                                                <div class="ms-4 mt-2">
                                                    @foreach($clubs as $club)
                                                        @php
                                                            $clubKey = $club['club_key'];
                                                        @endphp
                                                        <details class="mb-2" open>
                                                            <summary class="d-flex align-items-center gap-2">
                                                                <input type="checkbox"
                                                                       class="form-check-input tree-results-club"
                                                                       data-nation="{{ $nation ?? '' }}"
                                                                       data-club="{{ $clubKey }}">
                                                                <span>{{ $club['club_name'] ?: '—' }}</span>
                                                            </summary>

                                                            <div class="ms-4 mt-2">
                                                                @foreach($club['athletes'] as $ath)
                                                                    <div class="form-check">
                                                                        <input
                                                                            class="form-check-input tree-results-athlete"
                                                                            type="checkbox"
                                                                            data-nation="{{ $nation ?? '' }}"
                                                                            data-club="{{ $clubKey }}"
                                                                            data-athlete="{{ $ath['athlete_id'] }}">
                                                                        <label class="form-check-label">
                                                                            {{ $ath['name'] }}
                                                                            <span class="text-muted small">({{ $ath['count'] }})</span>
                                                                        </label>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </details>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if($doRelays)
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong>Staffeln</strong>
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                id="toggleAllRelays">Alle gültigen
                                        </button>
                                    </div>

                                    <div class="mt-2">
                                        @foreach($relaysTree as $nation => $clubs)
                                            <details class="mb-2" open>
                                                <summary class="d-flex align-items-center gap-2">
                                                    <input type="checkbox" class="form-check-input tree-relays-nation"
                                                           data-nation="{{ $nation ?? '' }}">
                                                    <span>{{ $nation ?: '—' }}</span>
                                                </summary>

                                                <div class="ms-4 mt-2">
                                                    @foreach($clubs as $club)
                                                        @php
                                                            $clubKey = $club['club_key'];
                                                        @endphp
                                                        <details class="mb-2" open>
                                                            <summary class="d-flex align-items-center gap-2">
                                                                <input type="checkbox"
                                                                       class="form-check-input tree-relays-club"
                                                                       data-nation="{{ $nation ?? '' }}"
                                                                       data-club="{{ $clubKey }}">
                                                                <span>{{ $club['club_name'] ?: '—' }}</span>
                                                            </summary>

                                                            <div class="ms-4 mt-2">
                                                                @foreach($club['relays'] as $r)
                                                                    <div class="form-check">
                                                                        <input
                                                                            class="form-check-input tree-relays-relay"
                                                                            type="checkbox"
                                                                            data-nation="{{ $nation ?? '' }}"
                                                                            data-club="{{ $clubKey }}"
                                                                            data-relay="{{ $r['key'] }}"
                                                                            {{ $r['invalid'] ? 'disabled' : '' }}>
                                                                        <label
                                                                            class="form-check-label {{ $r['invalid'] ? 'text-danger' : '' }}">
                                                                            {{ $r['label'] }}
                                                                        </label>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </details>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                        </div>
                    </div>
                </div>

                {{-- RIGHT: Preview tables --}}
                <div class="col-lg-8">
                    <div class="d-flex gap-2 mb-3">
                        <a class="btn btn-sm btn-link" href="{{ route('meets.lenex.results-wizard.form', $meet) }}">New
                            file</a>
                        <a class="btn btn-sm btn-link" href="{{ route('meets.show', $meet) }}">Back to Meet</a>
                    </div>

                    @if($doResults)
                        <h2 class="mt-2">Athlete Results</h2>

                        @forelse($resultsClubs as $club)
                            @php
                                $nation = $club['nation'] ?? '';
                                $clubName = $club['club_name'] ?? '';
                                $clubKey = $clubKeyFn($nation, $clubName);
                            @endphp

                            <h5 class="mt-3">{{ $clubName ?: '—' }} <small class="text-muted">{{ $nation }}</small></h5>

                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                    <tr>
                                        <th style="width:80px;">Import</th>
                                        <th>Event</th>
                                        <th>Athlete</th>
                                        <th>Time</th>
                                        <th>Splits</th>
                                        <th>Warnings</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach(($club['rows'] ?? []) as $row)
                                        @php
                                            $invalid = (bool)($row['invalid'] ?? false);
                                            $athId = (string)($row['lenex_athlete_id'] ?? '');
                                            $rowKey = $row['result_key'];
                                        @endphp

                                        <tr data-row-type="result"
                                            data-nation="{{ $nation }}"
                                            data-club="{{ $clubKey }}"
                                            data-athlete="{{ $athId }}"
                                            class="{{ $invalid ? 'table-danger' : '' }}">
                                            <td>
                                                <input class="form-check-input result-cb"
                                                       type="checkbox"
                                                       name="selected_results[]"
                                                       value="{{ $rowKey }}"
                                                       data-row-key="{{ $rowKey }}"
                                                    {{ $invalid ? 'disabled' : '' }}>
                                            </td>

                                            <td>
                                                <strong>{{ $row['event_label'] ?? '—' }}</strong>
                                                <div class="text-muted small">LENEX
                                                    eventid: {{ $row['lenex_eventid'] ?? '—' }}</div>
                                            </td>

                                            <td>
                                                {{ $row['last_name'] ?? '' }}, {{ $row['first_name'] ?? '' }}

                                                <span
                                                    class="badge bg-light text-dark border ms-2 {{ empty($row['sport_class_label']) ? 'text-danger' : '' }}">
                                                    SK {{ $row['sport_class_label'] ?? '—' }}
                                                </span>

                                                <span class="text-muted small ms-2">
                                                    ({{ $row['birth_year'] ?? '—' }})
                                                </span>


                                                <div class="text-muted small">
                                                    LENEX athleteid: {{ $row['lenex_athlete_id'] ?? '—' }}
                                                    @if(!empty($row['db_athlete_id']))
                                                        · DB#{{ $row['db_athlete_id'] }}
                                                    @endif
                                                </div>
                                            </td>

                                            <td>{{ $row['time_fmt'] ?? ($row['swimtime'] ?? '—') }}</td>

                                            <td>
                                                @if(!empty($row['splits']))
                                                    @foreach($row['splits'] as $s)
                                                        <div class="small">{{ $s['distance'] ?? '—' }}
                                                            m: {{ $s['time_fmt'] ?? '—' }}</div>
                                                    @endforeach
                                                @else
                                                    —
                                                @endif
                                            </td>

                                            <td>
                                                @if($invalid)
                                                    <ul class="mb-0">
                                                        @foreach(($row['invalid_reasons'] ?? []) as $r)
                                                            <li>{{ $r }}</li>
                                                        @endforeach
                                                    </ul>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @empty
                            <div class="alert alert-info">No Athlete Results found.</div>
                        @endforelse
                    @endif

                    @if($doRelays)
                        <h2 class="mt-5">Relays</h2>

                        @php
                            $byNation = collect($relaysClubs)->groupBy('nation');
                        @endphp
                        @forelse($byNation as $nation => $nationClubs)
                            <h5 class="mt-3">{{ $nation ?: '—' }}</h5>

                            @foreach($nationClubs as $club)
                                @php
                                    $clubName = $club['club_name'] ?? '';
                                    $clubKey = $clubKeyFn($nation, $clubName);
                                @endphp

                                <h6 class="mt-2">{{ $clubName ?: '—' }}</h6>

                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                        <tr>
                                            <th style="width:80px;">Import</th>
                                            <th>Event</th>
                                            <th>Relay</th>
                                            <th>Time</th>
                                            <th>Members (Sportclass)</th>
                                            <th>Warnings</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach(($club['relay_rows'] ?? []) as $row)
                                            @php
                                                $invalid = (bool)($row['invalid'] ?? false);
                                                $key = $row['lenex_resultid'] ?: $row['result_id'];
                                            @endphp

                                            <tr data-row-type="relay"
                                                data-nation="{{ $nation }}"
                                                data-club="{{ $clubKey }}"
                                                data-relay="{{ $key }}"
                                                class="{{ $invalid ? 'table-danger' : '' }}">
                                                <td>
                                                    <input class="form-check-input relay-cb"
                                                           type="checkbox"
                                                           name="selected_relays[]"
                                                           value="{{ $key }}"
                                                           data-row-key="{{ $key }}"
                                                        {{ $invalid ? 'disabled' : '' }}>
                                                </td>

                                                <td>
                                                    <strong>{{ $row['relay_event_label'] ?? '—' }}</strong>
                                                    @if(!empty($row['relay_sportclass']))
                                                        <span
                                                            class="badge bg-secondary ms-2">{{ $row['relay_sportclass'] }}</span>
                                                    @endif
                                                    @if(!empty($row['agegroup_name']))
                                                        <div class="text-muted small">{{ $row['agegroup_name'] }}</div>
                                                    @endif
                                                    <div class="text-muted small">LENEX
                                                        eventid: {{ $row['lenex_eventid'] ?? '—' }}</div>
                                                </td>

                                                <td>
                                                    <strong>#{{ $row['relay_number'] ?? '—' }}</strong> {{ $row['relay_gender'] ?? '' }}
                                                </td>
                                                <td>{{ $row['swimtime_fmt'] ?? ($row['swimtime'] ?? '—') }}</td>

                                                <td>
                                                    @foreach(($row['positions'] ?? []) as $p)
                                                        <div>
                                                            <strong>{{ $p['leg'] ?? '' }}.</strong>
                                                            {{ $p['last_name'] ?? '' }}, {{ $p['first_name'] ?? '' }}
                                                            <span
                                                                class="{{ (!($p['in_lenex_club'] ?? true) || !($p['exists_in_db'] ?? true)) ? 'text-danger' : 'text-success' }}">
                                                            ({{ $p['lenex_athlete_id'] ?? '—' }})
                                                        </span>
                                                            <span
                                                                class="badge bg-light text-dark border ms-2 {{ empty($p['sport_class']) ? 'text-danger' : '' }}">
                                                            SK {{ $p['sport_class'] ?? '—' }}
                                                        </span>
                                                        </div>
                                                    @endforeach
                                                </td>

                                                <td>
                                                    @if($invalid)
                                                        <ul class="mb-0">
                                                            @foreach(($row['invalid_reasons'] ?? []) as $r)
                                                                <li>{{ $r }}</li>
                                                            @endforeach
                                                        </ul>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endforeach
                        @empty
                            <div class="alert alert-info">No Relay results found.</div>
                        @endforelse
                    @endif

                    <button class="btn btn-primary mt-4">Import selected</button>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const showSelectedOnly = document.getElementById('showSelectedOnly');

            function setChecked(selector, checked) {
                document.querySelectorAll(selector).forEach(cb => {
                    if (cb.disabled) return;
                    cb.checked = checked;
                });
            }

            function applyFilter() {
                const only = !!showSelectedOnly?.checked;

                document.querySelectorAll('tr[data-row-type="result"]').forEach(tr => {
                    const cb = tr.querySelector('input.result-cb');
                    tr.style.display = (!only || (cb && cb.checked)) ? '' : 'none';
                });

                document.querySelectorAll('tr[data-row-type="relay"]').forEach(tr => {
                    const cb = tr.querySelector('input.relay-cb');
                    tr.style.display = (!only || (cb && cb.checked)) ? '' : 'none';
                });
            }

            // Buttons: toggle all valid
            document.getElementById('toggleAllResults')?.addEventListener('click', () => {
                const boxes = Array.from(document.querySelectorAll('.result-cb')).filter(cb => !cb.disabled);
                const anyUnchecked = boxes.some(cb => !cb.checked);
                boxes.forEach(cb => cb.checked = anyUnchecked);
                applyFilter();
            });

            document.getElementById('toggleAllRelays')?.addEventListener('click', () => {
                const boxes = Array.from(document.querySelectorAll('.relay-cb')).filter(cb => !cb.disabled);
                const anyUnchecked = boxes.some(cb => !cb.checked);
                boxes.forEach(cb => cb.checked = anyUnchecked);
                applyFilter();
            });

            // Tree: Results
            document.addEventListener('change', (e) => {
                const t = e.target;

                if (t.classList.contains('tree-results-nation')) {
                    const nation = t.dataset.nation ?? '';
                    setChecked(`.result-cb:not([disabled]) ~ *`, false); // no-op; safe
                    document.querySelectorAll(`tr[data-row-type="result"][data-nation="${CSS.escape(nation)}"] .result-cb`).forEach(cb => {
                        if (!cb.disabled) cb.checked = t.checked;
                    });
                    applyFilter();
                }

                if (t.classList.contains('tree-results-club')) {
                    const nation = t.dataset.nation ?? '';
                    const club = t.dataset.club ?? '';
                    document.querySelectorAll(`tr[data-row-type="result"][data-nation="${CSS.escape(nation)}"][data-club="${CSS.escape(club)}"] .result-cb`).forEach(cb => {
                        if (!cb.disabled) cb.checked = t.checked;
                    });
                    applyFilter();
                }

                if (t.classList.contains('tree-results-athlete')) {
                    const nation = t.dataset.nation ?? '';
                    const club = t.dataset.club ?? '';
                    const athlete = t.dataset.athlete ?? '';
                    document.querySelectorAll(`tr[data-row-type="result"][data-nation="${CSS.escape(nation)}"][data-club="${CSS.escape(club)}"][data-athlete="${CSS.escape(athlete)}"] .result-cb`).forEach(cb => {
                        if (!cb.disabled) cb.checked = t.checked;
                    });
                    applyFilter();
                }

                // Tree: Relays
                if (t.classList.contains('tree-relays-nation')) {
                    const nation = t.dataset.nation ?? '';
                    document.querySelectorAll(`tr[data-row-type="relay"][data-nation="${CSS.escape(nation)}"] .relay-cb`).forEach(cb => {
                        if (!cb.disabled) cb.checked = t.checked;
                    });
                    applyFilter();
                }

                if (t.classList.contains('tree-relays-club')) {
                    const nation = t.dataset.nation ?? '';
                    const club = t.dataset.club ?? '';
                    document.querySelectorAll(`tr[data-row-type="relay"][data-nation="${CSS.escape(nation)}"][data-club="${CSS.escape(club)}"] .relay-cb`).forEach(cb => {
                        if (!cb.disabled) cb.checked = t.checked;
                    });
                    applyFilter();
                }

                if (t.classList.contains('tree-relays-relay')) {
                    const key = t.dataset.relay ?? '';
                    document.querySelectorAll(`tr[data-row-type="relay"][data-relay="${CSS.escape(key)}"] .relay-cb`).forEach(cb => {
                        if (!cb.disabled) cb.checked = t.checked;
                    });
                    applyFilter();
                }

                if (t === showSelectedOnly) {
                    applyFilter();
                }

                if (t.classList.contains('result-cb') || t.classList.contains('relay-cb')) {
                    applyFilter();
                }
            });

            applyFilter();
        })();
    </script>
@endpush
