@extends('layouts.app')

@section('content')
    @php
        $resultsClubs = $doResults ? ($resultsData['clubs'] ?? []) : [];
        $relaysClubs  = $doRelays  ? ($relaysData['clubs'] ?? [])  : [];

        // stabile club keys (Nation + Clubname)
        $clubKeyFn = function ($nation, $clubName) {
            return md5(((string)($nation ?? '')) . '|' . ((string)($clubName ?? '')));
        };

        // RESULTS Tree: Nation -> Clubs -> Athletes
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
                                'athlete_id' => (string)$athId, // LENEX athleteid
                                'name'       => $name !== '' ? $name : ('Athlete '.$athId),
                                'count'      => $aRows->count(),
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

        // RELAYS Tree: Nation -> Clubs -> Relay rows
        $relaysTree = collect($relaysClubs)
            ->groupBy('nation')
            ->map(function($clubs, $nation) use ($clubKeyFn) {
                return collect($clubs)->map(function($club) use ($nation, $clubKeyFn) {
                    $clubName = $club['club_name'] ?? '';
                    $clubKey  = $clubKeyFn($nation, $clubName);

                    $relays = collect($club['relay_rows'] ?? [])->map(function($row) {
                        $key = $row['lenex_resultid'] ?: ($row['result_id'] ?? '');
                        $label = trim(($row['relay_event_label'] ?? '—').' · #'.($row['relay_number'] ?? '—'));
                        if (!empty($row['relay_sportclass'])) $label .= ' · '.$row['relay_sportclass'];

                        return [
                            'key'     => (string)$key,
                            'label'   => $label,
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

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div>
                <h1 class="mb-1">LENEX Import Wizard – Preview</h1>
                <div class="text-muted">
                    <strong>Meet:</strong> {{ $meet->name }}<br>
                    {{ $meet->city }}
                </div>
            </div>

            <div class="d-flex gap-3 pt-2">
                <a href="{{ route('meets.lenex.results-wizard.form', $meet) }}">New file</a>
                <a href="{{ route('meets.show', $meet) }}">Back to Meet</a>
            </div>
        </div>

        <form method="POST" action="{{ route('meets.lenex.results-wizard.import', $meet) }}">
            @csrf
            <input type="hidden" name="lenex_file_path"
                   value="{{ is_string($lenexFilePath ?? null) ? $lenexFilePath : '' }}">
            <input type="hidden" name="do_results" value="{{ $doResults ? 1 : 0 }}">
            <input type="hidden" name="do_relays" value="{{ $doRelays ? 1 : 0 }}">

            <div class="row g-3">
                {{-- LEFT: Selection --}}
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
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong>Athleten</strong>
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                id="toggleAllResults">
                                            Alle gültigen
                                        </button>
                                    </div>

                                    <div class="mt-2">
                                        @foreach($resultsTree as $nation => $clubs)
                                            <details class="mb-2" open>
                                                <summary class="d-flex align-items-center gap-2">
                                                    <input type="checkbox"
                                                           class="form-check-input tree-results-nation"
                                                           data-nation="{{ $nation ?? '' }}">
                                                    <span>{{ $nation ?: '—' }}</span>
                                                </summary>

                                                <div class="ms-4 mt-2">
                                                    @foreach($clubs as $club)
                                                        @php $clubKey = $club['club_key']; @endphp

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
                                <div class="mb-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong>Staffeln</strong>
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                id="toggleAllRelays">
                                            Alle gültigen
                                        </button>
                                    </div>

                                    <div class="mt-2">
                                        @foreach($relaysTree as $nation => $clubs)
                                            <details class="mb-2" open>
                                                <summary class="d-flex align-items-center gap-2">
                                                    <input type="checkbox"
                                                           class="form-check-input tree-relays-nation"
                                                           data-nation="{{ $nation ?? '' }}">
                                                    <span>{{ $nation ?: '—' }}</span>
                                                </summary>

                                                <div class="ms-4 mt-2">
                                                    @foreach($clubs as $club)
                                                        @php $clubKey = $club['club_key']; @endphp

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

                            <hr class="my-3">

                            <button type="submit" class="btn btn-primary w-100">
                                Import selected
                            </button>
                        </div>
                    </div>
                </div>

                {{-- RIGHT: Preview tables --}}
                <div class="col-lg-8">
                    @if($doResults)
                        <h2 class="mb-2">Athlete Results</h2>

                        @foreach(($resultsClubs ?? []) as $club)
                            @php
                                $nation   = $club['nation'] ?? '';
                                $clubName = $club['club_name'] ?? '';
                                $clubKey  = $clubKeyFn($nation, $clubName);
                                $rows     = $club['rows'] ?? [];
                            @endphp

                            <div class="mb-4 result-club-block"
                                 data-block-type="result"
                                 data-nation="{{ $nation }}"
                                 data-club="{{ $clubKey }}">
                                <div class="fw-semibold mb-2">
                                    {{ $clubName ?: '—' }} <span class="text-muted">{{ $nation }}</span>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                        <tr>
                                            <th style="width: 70px;">Import</th>
                                            <th>Event</th>
                                            <th>Athlete</th>
                                            <th style="width: 110px;">Time</th>
                                            <th style="width: 180px;">Splits</th>
                                            <th>Warnings</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($rows as $row)
                                            @php
                                                $athId   = (string)($row['lenex_athlete_id'] ?? '');
                                                $rowKey  = (string)(($row['lenex_resultid'] ?? '') ?: ($row['result_id'] ?? ''));
                                                $invalid = (bool)($row['invalid'] ?? false);
                                                $eventLabel = $row['event_label'] ?? $row['event'] ?? '—';
                                                $athName = trim(($row['last_name'] ?? '').', '.($row['first_name'] ?? ''), ', ');
                                                $time = $row['time_fmt'] ?? ($row['swimtime'] ?? '—');
                                                $splits = $row['splits_label'] ?? $row['splits'] ?? '—';
                                                $warnings = $row['invalid_reasons'] ?? [];
                                                if (is_string($warnings)) $warnings = [$warnings];
                                                $sportClassLabel = $row['sport_class_label'] ?? null;
                                                $birthYear = $row['birth_year'] ?? null;
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
                                                    <div class="fw-semibold">{{ $eventLabel }}</div>
                                                    <div class="text-muted small">
                                                        @if(!empty($row['lenex_eventid']))
                                                            LENEX eventid: {{ $row['lenex_eventid'] }}
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    <div
                                                        class="fw-semibold">{{ $athName !== '' ? $athName : ('Athlete '.$athId) }}</div>
                                                    <div class="text-muted small">
                                                        @if(!empty($sportClassLabel))
                                                            {{ $sportClassLabel }}
                                                        @endif
                                                        @if(!empty($birthYear))
                                                            ({{ $birthYear }})
                                                        @endif
                                                        <span class="ms-2">LENEX athleteid: {{ $athId }}</span>
                                                    </div>
                                                </td>
                                                <td>{{ $time }}</td>
                                                <td class="text-muted">
                                                    @if(is_array($splits) && !empty($splits))
                                                        @foreach($splits as $sp)
                                                            @php
                                                                $dist = $sp['distance'] ?? null;
                                                                $tf   = $sp['time_fmt'] ?? ($sp['swimtime'] ?? '—');
                                                            @endphp
                                                            <div>{{ $dist ? ($dist.' m: '.$tf) : $tf }}</div>
                                                        @endforeach
                                                    @else
                                                        {{ is_scalar($splits) ? $splits : '—' }}
                                                    @endif
                                                </td>

                                                <td>
                                                    @if(!empty($warnings))
                                                        <ul class="mb-0 ps-3">
                                                            @foreach($warnings as $w)
                                                                <li>{{ $w }}</li>
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
                            </div>
                        @endforeach
                    @endif

                    @if($doRelays)
                        <h2 class="mb-2">Relays</h2>

                        @foreach(($relaysClubs ?? []) as $club)
                            @php
                                $nation   = $club['nation'] ?? '';
                                $clubName = $club['club_name'] ?? '';
                                $clubKey  = $clubKeyFn($nation, $clubName);
                                $rows     = $club['relay_rows'] ?? [];
                            @endphp

                            <div class="mb-4 relay-club-block"
                                 data-block-type="relay"
                                 data-nation="{{ $nation }}"
                                 data-club="{{ $clubKey }}">
                                <div class="fw-semibold mb-2">
                                    {{ $clubName ?: '—' }} <span class="text-muted">{{ $nation }}</span>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead>
                                        <tr>
                                            <th style="width: 70px;">Import</th>
                                            <th>Relay</th>
                                            <th>Time</th>
                                            <th>Warnings</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($rows as $row)
                                            @php
                                                $rowKey  = (string)(($row['lenex_resultid'] ?? '') ?: ($row['result_id'] ?? ''));
                                                $invalid = (bool)($row['invalid'] ?? false);
                                                $label   = $row['relay_event_label'] ?? '—';
                                                $num     = $row['relay_number'] ?? '—';
                                                $time = $row['swimtime_fmt'] ?? ($row['swimtime'] ?? '—');
                                                $warnings = $row['warnings'] ?? [];
                                                if (is_string($warnings)) $warnings = [$warnings];
                                            @endphp

                                            <tr data-row-type="relay"
                                                data-nation="{{ $nation }}"
                                                data-club="{{ $clubKey }}"
                                                data-relay="{{ $rowKey }}"
                                                class="{{ $invalid ? 'table-danger' : '' }}">
                                                <td>
                                                    <input class="form-check-input relay-cb"
                                                           type="checkbox"
                                                           name="selected_relays[]"
                                                           value="{{ $rowKey }}"
                                                           data-row-key="{{ $rowKey }}"
                                                        {{ $invalid ? 'disabled' : '' }}>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold">{{ $label }} · #{{ $num }}</div>
                                                    <div class="text-muted small">
                                                        @if(!empty($row['relay_sportclass']))
                                                            {{ $row['relay_sportclass'] }}
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>{{ $time }}</td>
                                                <td>
                                                    @php
                                                        $reasons = $row['invalid_reasons'] ?? ($row['warnings'] ?? []);
                                                        if (is_string($reasons)) $reasons = [$reasons];
                                                    @endphp
                                                    @if(!empty($reasons))
                                                        <ul class="mb-0 ps-3">
                                                            @foreach($reasons as $r)
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
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </form>
    </div>

    <script>
        (function () {
            /** @returns {HTMLInputElement|null} */
            function byIdInput(id) {
                const el = document.getElementById(id);
                return el instanceof HTMLInputElement ? el : null;
            }

            /**
             * @param {string} sel
             * @param {ParentNode} [root]
             * @returns {HTMLInputElement[]}
             */
            function inputs(sel, root) {
                const r = root ?? document;

                /** @type {HTMLInputElement[]} */
                const out = [];

                r.querySelectorAll(sel).forEach((el) => {
                    if (el instanceof HTMLInputElement) out.push(el);
                });

                return out;
            }

            /**
             * @param {string} sel
             * @param {*} [root]
             * @returns {HTMLTableRowElement[]}
             */
            function rows(sel, root) {
                const r = root ?? document;
                /** @type {HTMLTableRowElement[]} */
                const out = [];
                r.querySelectorAll(sel).forEach(el => {
                    if (el instanceof HTMLTableRowElement) out.push(el);
                });
                return out;
            }

            /**
             * @param {string} sel
             * @param {*} [root]
             * @returns {HTMLElement[]}
             */
            function els(sel, root) {
                const r = root ?? document;
                /** @type {HTMLElement[]} */
                const out = [];
                r.querySelectorAll(sel).forEach(el => {
                    if (el instanceof HTMLElement) out.push(el);
                });
                return out;
            }

            /**
             * @param {HTMLInputElement[]} checkboxes
             * @param {boolean} isChecked
             */
            function setChecked(checkboxes, isChecked) {
                checkboxes.forEach(cb => {
                    if (cb.disabled) return;
                    cb.checked = isChecked;
                });
            }

            function escAttr(val) {
                const v = String(val ?? '');
                if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(v);
                return v.replace(/["\\]/g, '\\$&');
            }

            const showSelectedOnly = byIdInput('showSelectedOnly');

            function applyFilter() {
                const only = !!showSelectedOnly?.checked;

                if (!only) {
                    rows('tr[data-row-type="result"]').forEach(tr => tr.hidden = false);
                    rows('tr[data-row-type="relay"]').forEach(tr => tr.hidden = false);
                    els('.result-club-block').forEach(b => b.hidden = false);
                    els('.relay-club-block').forEach(b => b.hidden = false);
                    return;
                }

                // --- LEFT selection sets (Results) ---
                const selResNations = new Set(inputs('.tree-results-nation:checked').map(cb => cb.dataset.nation ?? ''));
                const selResClubs = new Set(inputs('.tree-results-club:checked').map(cb => `${cb.dataset.nation ?? ''}|${cb.dataset.club ?? ''}`));
                const selAthletes = new Set(inputs('.tree-results-athlete:checked').map(cb => cb.dataset.athlete ?? ''));

                rows('tr[data-row-type="result"]').forEach(tr => {
                    const n = tr.dataset.nation ?? '';
                    const c = tr.dataset.club ?? '';
                    const a = tr.dataset.athlete ?? '';
                    const show = selResNations.has(n) || selResClubs.has(`${n}|${c}`) || selAthletes.has(a);
                    tr.hidden = !show;
                });

                // --- LEFT selection sets (Relays) ---
                const selRelNations = new Set(inputs('.tree-relays-nation:checked').map(cb => cb.dataset.nation ?? ''));
                const selRelClubs = new Set(inputs('.tree-relays-club:checked').map(cb => `${cb.dataset.nation ?? ''}|${cb.dataset.club ?? ''}`));
                const selRelays = new Set(inputs('.tree-relays-relay:checked').map(cb => cb.dataset.relay ?? ''));

                rows('tr[data-row-type="relay"]').forEach(tr => {
                    const n = tr.dataset.nation ?? '';
                    const c = tr.dataset.club ?? '';
                    const r = tr.dataset.relay ?? '';
                    const show = selRelNations.has(n) || selRelClubs.has(`${n}|${c}`) || selRelays.has(r);
                    tr.hidden = !show;
                });

                // Hide empty blocks
                els('.result-club-block').forEach(block => {
                    const anyVisible = rows('tr[data-row-type="result"]', block).some(tr => !tr.hidden);
                    block.hidden = !anyVisible;
                });

                els('.relay-club-block').forEach(block => {
                    const anyVisible = rows('tr[data-row-type="relay"]', block).some(tr => !tr.hidden);
                    block.hidden = !anyVisible;
                });
            }

            // Toggle all valid results / relays
            document.getElementById('toggleAllResults')?.addEventListener('click', () => {
                const boxes = inputs('.result-cb').filter(cb => !cb.disabled);
                const anyUnchecked = boxes.some(cb => !cb.checked);
                setChecked(boxes, anyUnchecked);
                applyFilter();
            });

            document.getElementById('toggleAllRelays')?.addEventListener('click', () => {
                const boxes = inputs('.relay-cb').filter(cb => !cb.disabled);
                const anyUnchecked = boxes.some(cb => !cb.checked);
                setChecked(boxes, anyUnchecked);
                applyFilter();
            });

            // Event delegation
            document.addEventListener('change', (e) => {
                const t = e.target;

                // direct click on import checkboxes or filter checkbox
                if (t instanceof HTMLInputElement && (t.classList.contains('result-cb') || t.classList.contains('relay-cb') || t.id === 'showSelectedOnly')) {
                    applyFilter();
                    return;
                }

                if (!(t instanceof HTMLInputElement)) return;

                // RESULTS tree
                if (t.classList.contains('tree-results-nation')) {
                    const nation = escAttr(t.dataset.nation ?? '');
                    const boxes = inputs(`tr[data-row-type="result"][data-nation="${nation}"] .result-cb`);
                    setChecked(boxes, t.checked);
                    applyFilter();
                    return;
                }

                if (t.classList.contains('tree-results-club')) {
                    const nation = escAttr(t.dataset.nation ?? '');
                    const club = escAttr(t.dataset.club ?? '');
                    const boxes = inputs(`tr[data-row-type="result"][data-nation="${nation}"][data-club="${club}"] .result-cb`);
                    setChecked(boxes, t.checked);
                    applyFilter();
                    return;
                }

                if (t.classList.contains('tree-results-athlete')) {
                    const nation = escAttr(t.dataset.nation ?? '');
                    const club = escAttr(t.dataset.club ?? '');
                    const athlete = escAttr(t.dataset.athlete ?? '');
                    const boxes = inputs(`tr[data-row-type="result"][data-nation="${nation}"][data-club="${club}"][data-athlete="${athlete}"] .result-cb`);
                    setChecked(boxes, t.checked);
                    applyFilter();
                    return;
                }

                // RELAYS tree
                if (t.classList.contains('tree-relays-nation')) {
                    const nation = escAttr(t.dataset.nation ?? '');
                    const boxes = inputs(`tr[data-row-type="relay"][data-nation="${nation}"] .relay-cb`);
                    setChecked(boxes, t.checked);
                    applyFilter();
                    return;
                }

                if (t.classList.contains('tree-relays-club')) {
                    const nation = escAttr(t.dataset.nation ?? '');
                    const club = escAttr(t.dataset.club ?? '');
                    const boxes = inputs(`tr[data-row-type="relay"][data-nation="${nation}"][data-club="${club}"] .relay-cb`);
                    setChecked(boxes, t.checked);
                    applyFilter();
                    return;
                }

                if (t.classList.contains('tree-relays-relay')) {
                    // aktuell kein Mapping nötig, da Relay-Tree nicht direkt Table-Checkboxes mapped
                    applyFilter();

                }
            });

            showSelectedOnly?.addEventListener('change', applyFilter);
            applyFilter();
        })();
    </script>

@endsection
