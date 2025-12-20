@extends('layouts.app')

@section('content')
    @php
        $resultsClubs = $doResults ? ($resultsData['clubs'] ?? []) : [];
        $relaysClubs  = $doRelays  ? ($relaysData['clubs'] ?? [])  : [];

        $clubKeyFn = function ($nation, $clubName) {
            return md5(((string)($nation ?? '')) . '|' . ((string)($clubName ?? '')));
        };
    @endphp

    <div class="container py-4">
        <h2 class="mb-3">LENEX Wizard Preview</h2>

        <form method="POST" action="{{ route('meets.lenex.results-wizard.import', $meet) }}">
            @csrf
            <input type="hidden" name="lenex_file_path" value="{{ $lenexFilePath }}">

            <div class="row g-4">
                {{-- LEFT --}}
                <div class="col-12 col-lg-4">
                    <div class="card">
                        <div class="card-header fw-semibold">Auswahl / Filter</div>
                        <div class="card-body">

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="onlySelected" checked>
                                <label class="form-check-label" for="onlySelected">nur ausgewählte anzeigen</label>
                                <div class="text-muted small mt-1">
                                    Zeigt nur Zeilen an, deren Import-Checkbox aktiviert ist.
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllValid">
                                    Alle gültigen Results
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="unselectAllResults">
                                    Results abwählen
                                </button>

                                <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllRelays">
                                    Alle gültigen Relays
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="unselectAllRelays">
                                    Relays abwählen
                                </button>
                            </div>

                            @php
                                // Nation/Club Tree aus Results + Relays
                                $tree = [];

                                // 1) Results rein
                                foreach ($resultsClubs as $club) {
                                    $nation = $club['nation'] ?? '';
                                    $clubName = $club['club_name'] ?? '';
                                    $clubKey = $clubKeyFn($nation, $clubName);

                                    $tree[$nation][$clubKey]['club_name'] = $clubName;
                                    $tree[$nation][$clubKey]['club_id'] = $club['club_id'] ?? null;
                                    $tree[$nation][$clubKey]['results_count'] = ($tree[$nation][$clubKey]['results_count'] ?? 0) + count($club['rows'] ?? []);
                                    $tree[$nation][$clubKey]['relays_count']  = $tree[$nation][$clubKey]['relays_count'] ?? 0;

                                    foreach (($club['rows'] ?? []) as $row) {
                                        $aid = (string)($row['lenex_athlete_id'] ?? '');
                                        if ($aid === '') continue;

                                        $tree[$nation][$clubKey]['athletes'][$aid] = $tree[$nation][$clubKey]['athletes'][$aid] ?? [
                                            'label' => ($row['last_name'] ?? '').', '.($row['first_name'] ?? ''),
                                            'count' => 0,
                                        ];
                                        $tree[$nation][$clubKey]['athletes'][$aid]['count']++;
                                    }
                                }

                                // 2) Relays rein (damit Clubs/Nationen im Filter sichtbar sind, auch ohne Results)
                                foreach ($relaysClubs as $club) {
                                    $nation = $club['nation'] ?? '';
                                    $clubName = $club['club_name'] ?? '';
                                    $clubKey = $clubKeyFn($nation, $clubName);

                                    $tree[$nation][$clubKey]['club_name'] = $tree[$nation][$clubKey]['club_name'] ?? $clubName;
                                    $tree[$nation][$clubKey]['club_id']   = $tree[$nation][$clubKey]['club_id'] ?? ($club['club_id'] ?? null);
                                    $tree[$nation][$clubKey]['results_count'] = $tree[$nation][$clubKey]['results_count'] ?? 0;
                                    $tree[$nation][$clubKey]['relays_count']  = ($tree[$nation][$clubKey]['relays_count'] ?? 0) + count($club['relay_rows'] ?? []);

                                    // keine athletes hier – Relays sind club/nation filterbar, athlete-tree bleibt für results
                                    $tree[$nation][$clubKey]['athletes'] = $tree[$nation][$clubKey]['athletes'] ?? [];
                                }

                                ksort($tree);
                            @endphp


                            <div class="fw-semibold mb-2">Tree (setzt Import-Checkboxen)</div>

                            @if(empty($tree))
                                <div class="text-muted small">Keine Athlete Results gefunden.</div>
                            @else
                                <div class="small" id="selectionTree">
                                    @foreach($tree as $nation => $clubs)
                                        @php $nid = 'n_'.md5($nation); @endphp

                                        <div class="mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input tree-nation" type="checkbox"
                                                       id="{{ $nid }}"
                                                       data-nation="{{ $nation }}">
                                                <label class="form-check-label fw-semibold" for="{{ $nid }}">
                                                    {{ $nation ?: '—' }}
                                                </label>
                                            </div>

                                            <div class="ms-3 mt-1">
                                                @foreach($clubs as $clubKey => $cdata)
                                                    @php
                                                        $cid = 'c_'.md5($nation.'|'.$clubKey);
                                                        $cname = $cdata['club_name'] ?? '';
                                                    @endphp
                                                    <div class="mb-1">
                                                        <div class="form-check">
                                                            <input class="form-check-input tree-club" type="checkbox"
                                                                   id="{{ $cid }}"
                                                                   data-nation="{{ $nation }}"
                                                                   data-club="{{ $clubKey }}">
                                                            @php
                                                                $rc = (int)($cdata['results_count'] ?? 0);
                                                                $lc = (int)($cdata['relays_count'] ?? 0);
                                                            @endphp

                                                            <label class="form-check-label" for="{{ $cid }}">
                                                                {{ $cname ?: '—' }}
                                                                <span class="text-muted ms-1">[Results: {{ $rc }} | Relays: {{ $lc }}]</span>
                                                            </label>
                                                        </div>

                                                        <div class="ms-3 mt-1">

                                                            @foreach(($cdata['athletes'] ?? []) as $aid => $adata)
                                                                @if(!empty($cdata['athletes']))
                                                                    @php
                                                                        $aidId = 'a_'.md5($nation.'|'.$clubKey.'|'.$aid);
                                                                    @endphp
                                                                    <div class="form-check">
                                                                        <input class="form-check-input tree-athlete"
                                                                               type="checkbox"
                                                                               id="{{ $aidId }}"
                                                                               data-nation="{{ $nation }}"
                                                                               data-club="{{ $clubKey }}"
                                                                               data-athlete="{{ $aid }}">
                                                                        <label class="form-check-label"
                                                                               for="{{ $aidId }}">
                                                                            {{ $adata['label'] ?? '—' }}
                                                                            <span class="text-muted">({{ $adata['count'] ?? 0 }})</span>
                                                                        </label>
                                                                    </div>
                                                                @else
                                                                    <div class="text-muted small">keine Athlete
                                                                        Results
                                                                    </div>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                        </div>
                    </div>
                </div>

                {{-- RIGHT --}}
                <div class="col-12 col-lg-8">

                    {{-- RESULTS --}}
                    @if($doResults)
                        <div class="card mb-3">
                            <div class="card-header fw-semibold">Athlete Results</div>
                            <div class="card-body">

                                @forelse($resultsClubs as $club)
                                    @php
                                        $nation = $club['nation'] ?? '';
                                        $clubName = $club['club_name'] ?? '';
                                        $clubKey = $clubKeyFn($nation, $clubName);

                                        $lenexClubId = $club['club_id'] ?? null;
                                        $clubSelected = $lenexClubId ? old("club_match.$lenexClubId", $club['club_match_selected'] ?? 'auto') : 'auto';
                                        $clubCands = $club['club_match_candidates'] ?? [];
                                    @endphp

                                    <div class="result-club-block mb-4"
                                         data-nation="{{ $nation }}"
                                         data-club="{{ $clubKey }}">
                                        <div class="d-flex align-items-start justify-content-between gap-2">
                                            <div>
                                                <h6 class="mb-1">{{ $clubName ?: '—' }} <small
                                                        class="text-muted">{{ $nation }}</small></h6>
                                                <div class="text-muted small">LENEX
                                                    clubid: {{ $lenexClubId ?: '—' }}</div>
                                            </div>

                                            @if($lenexClubId)
                                                <div style="min-width: 320px; max-width: 520px;">
                                                    <label class="form-label small text-muted mb-1">Club-Match</label>
                                                    <select name="club_match[{{ $lenexClubId }}]"
                                                            class="form-select form-select-sm club-match"
                                                            data-clubid="{{ $lenexClubId }}">
                                                        <option
                                                            value="auto" {{ (string)$clubSelected === 'auto' ? 'selected' : '' }}>
                                                            Auto (tmId/Name)
                                                        </option>
                                                        <option
                                                            value="new" {{ (string)$clubSelected === 'new' ? 'selected' : '' }}>
                                                            Neuen Club anlegen
                                                        </option>

                                                        @if(!empty($clubCands))
                                                            <option disabled>──────────</option>
                                                            @foreach($clubCands as $c)
                                                                @php
                                                                    $cid = (int)($c['id'] ?? 0);
                                                                    $score = (int)($c['score'] ?? 0);
                                                                    $label = (string)($c['label'] ?? '');
                                                                    $sel = ((string)$clubSelected === (string)$cid) ? 'selected' : '';
                                                                @endphp
                                                                <option value="{{ $cid }}" {{ $sel }}>
                                                                    [{{ $score }}] {{ $label }}
                                                                </option>
                                                            @endforeach
                                                        @endif
                                                    </select>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="table-responsive mt-2">
                                            <table class="table table-sm align-middle">
                                                <thead>
                                                <tr>
                                                    <th style="width:80px;">Import</th>
                                                    <th>Event</th>
                                                    <th>Athlete</th>
                                                    <th style="width:110px;">Time</th>
                                                    <th>Splits</th>
                                                    <th>Warnings</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                @foreach(($club['rows'] ?? []) as $row)
                                                    @php
                                                        $invalid = (bool)($row['invalid'] ?? false);
                                                        $warnings = $row['warnings'] ?? [];
                                                        $blockers = $row['invalid_reasons'] ?? [];
                                                        $hasWarn = !empty($warnings);
                                                        $rowClass = $invalid ? 'table-danger' : ($hasWarn ? 'table-warning' : '');
                                                        $athId = (string)($row['lenex_athlete_id'] ?? '');
                                                        $rowKey = (string)($row['result_key'] ?? '');
                                                        $selectedMatch = old("athlete_match.$athId", $row['match_selected'] ?? 'auto');
                                                        $cands = $row['match_candidates'] ?? [];
                                                    @endphp

                                                    <tr data-row-type="result"
                                                        data-nation="{{ $nation }}"
                                                        data-club="{{ $clubKey }}"
                                                        data-athlete="{{ $athId }}"
                                                        class="{{ $rowClass }}">
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
                                                            <div>
                                                                {{ ($row['last_name'] ?? '—') }}
                                                                , {{ ($row['first_name'] ?? '') }}
                                                                @if(!empty($row['sport_class_label']))
                                                                    <span
                                                                        class="badge bg-secondary ms-2">{{ $row['sport_class_label'] }}</span>
                                                                @endif
                                                                @if(!empty($row['birth_year']))
                                                                    <span class="text-muted ms-2">({{ $row['birth_year'] }})</span>
                                                                @endif
                                                            </div>
                                                            <div class="text-muted small">LENEX
                                                                athleteid: {{ $athId ?: '—' }}</div>

                                                            @if($athId !== '')
                                                                <div class="mt-1">
                                                                    <select name="athlete_match[{{ $athId }}]"
                                                                            class="form-select form-select-sm athlete-match"
                                                                            data-athlete="{{ $athId }}">
                                                                        <option
                                                                            value="auto" {{ (string)$selectedMatch === 'auto' ? 'selected' : '' }}>
                                                                            Auto (tmId/Service)
                                                                        </option>
                                                                        <option
                                                                            value="new" {{ (string)$selectedMatch === 'new' ? 'selected' : '' }}>
                                                                            Neu anlegen
                                                                        </option>

                                                                        @if(!empty($cands))
                                                                            <option disabled>──────────</option>
                                                                            @foreach($cands as $c)
                                                                                @php
                                                                                    $cid = (int)($c['id'] ?? 0);
                                                                                    $score = (int)($c['score'] ?? 0);
                                                                                    $label = (string)($c['label'] ?? '');
                                                                                    $sel = ((string)$selectedMatch === (string)$cid) ? 'selected' : '';
                                                                                @endphp
                                                                                <option value="{{ $cid }}" {{ $sel }}>
                                                                                    [{{ $score }}] {{ $label }}
                                                                                </option>
                                                                            @endforeach
                                                                        @endif
                                                                    </select>
                                                                </div>
                                                            @endif
                                                        </td>

                                                        <td>{{ $row['time_fmt'] ?? ($row['swimtime'] ?? '—') }}</td>

                                                        <td class="text-muted">
                                                            {{ $row['splits_label'] ?? '—' }}
                                                        </td>

                                                        <td>
                                                            @if(!empty($blockers) || !empty($warnings))
                                                                <ul class="mb-0 ps-3 small">
                                                                    @foreach($blockers as $r)
                                                                        <li class="text-danger">{{ $r }}</li>
                                                                    @endforeach
                                                                    @foreach($warnings as $r)
                                                                        <li>{{ $r }}</li>
                                                                    @endforeach
                                                                </ul>
                                                            @else
                                                                <span class="text-muted small">—</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-muted">Keine Athlete Results.</div>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    {{-- RELAYS --}}
                    @if($doRelays)
                        <div class="card">
                            <div class="card-header fw-semibold">Relays</div>
                            <div class="card-body">

                                @forelse($relaysClubs as $club)
                                    @php
                                        $nation = $club['nation'] ?? '';
                                        $clubName = $club['club_name'] ?? '';
                                        $clubKey = $clubKeyFn($nation, $clubName);

                                        $lenexClubId = $club['club_id'] ?? null;
                                        $clubSelected = $lenexClubId ? old("club_match.$lenexClubId", $club['club_match_selected'] ?? 'auto') : 'auto';
                                        $clubCands = $club['club_match_candidates'] ?? [];
                                    @endphp

                                    <div class="relay-club-block mb-4"
                                         data-nation="{{ $nation }}"
                                         data-club="{{ $clubKey }}">
                                        <div class="d-flex align-items-start justify-content-between gap-2">
                                            <div>
                                                <h6 class="mb-1">{{ $clubName ?: '—' }} <small
                                                        class="text-muted">{{ $nation }}</small></h6>
                                                <div class="text-muted small">LENEX
                                                    clubid: {{ $lenexClubId ?: '—' }}</div>
                                            </div>

                                            @if($lenexClubId)
                                                <div style="min-width: 320px; max-width: 520px;">
                                                    <label class="form-label small text-muted mb-1">Club-Match</label>
                                                    <select name="club_match[{{ $lenexClubId }}]"
                                                            class="form-select form-select-sm club-match"
                                                            data-clubid="{{ $lenexClubId }}">
                                                        <option
                                                            value="auto" {{ (string)$clubSelected === 'auto' ? 'selected' : '' }}>
                                                            Auto (tmId/Name)
                                                        </option>
                                                        <option
                                                            value="new" {{ (string)$clubSelected === 'new' ? 'selected' : '' }}>
                                                            Neuen Club anlegen
                                                        </option>

                                                        @if(!empty($clubCands))
                                                            <option disabled>──────────</option>
                                                            @foreach($clubCands as $c)
                                                                @php
                                                                    $cid = (int)($c['id'] ?? 0);
                                                                    $score = (int)($c['score'] ?? 0);
                                                                    $label = (string)($c['label'] ?? '');
                                                                    $sel = ((string)$clubSelected === (string)$cid) ? 'selected' : '';
                                                                @endphp
                                                                <option value="{{ $cid }}" {{ $sel }}>
                                                                    [{{ $score }}] {{ $label }}
                                                                </option>
                                                            @endforeach
                                                        @endif
                                                    </select>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="table-responsive mt-2">
                                            <table class="table table-sm align-middle">
                                                <thead>
                                                <tr>
                                                    <th style="width:80px;">Import</th>
                                                    <th>Event</th>
                                                    <th>Relay</th>
                                                    <th style="width:110px;">Time</th>
                                                    <th>Members (Sportclass)</th>
                                                    <th>Warnings</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                @foreach(($club['relay_rows'] ?? []) as $row)
                                                    @php
                                                        $invalid = (bool)($row['invalid'] ?? false);
                                                        $warnings = $row['warnings'] ?? [];
                                                        $blockers = $row['invalid_reasons'] ?? [];
                                                        $hasWarn = !empty($warnings);
                                                        $rowClass = $invalid ? 'table-danger' : ($hasWarn ? 'table-warning' : '');
                                                        $key = $row['lenex_resultid'] ?: $row['result_id'];
                                                    @endphp

                                                    <tr data-row-type="relay"
                                                        data-nation="{{ $nation }}"
                                                        data-club="{{ $clubKey }}"
                                                        data-relay="{{ $key }}"
                                                        class="{{ $rowClass }}">
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
                                                                <div
                                                                    class="text-muted small">{{ $row['agegroup_name'] }}</div>
                                                            @endif
                                                            <div class="text-muted small">LENEX
                                                                eventid: {{ $row['lenex_eventid'] ?? '—' }}</div>
                                                        </td>

                                                        <td>
                                                            #{{ $row['relay_number'] ?? '—' }}
                                                            <span
                                                                class="text-muted small ms-2">{{ $row['relay_gender'] ?? '' }}</span>
                                                        </td>

                                                        <td>{{ $row['swimtime_fmt'] ?? ($row['swimtime'] ?? '—') }}</td>

                                                        <td>
                                                            @foreach(($row['positions'] ?? []) as $p)
                                                                @php
                                                                    $aid = (string)($p['lenex_athlete_id'] ?? '');
                                                                    $cands = $p['match_candidates'] ?? [];
                                                                    $selMatch = old("athlete_match.$aid", $p['match_selected'] ?? 'auto');
                                                                @endphp
                                                                <div class="mb-2">
                                                                    <div>
                                                                        {{ ($p['last_name'] ?? '—') }}
                                                                        , {{ ($p['first_name'] ?? '') }}
                                                                        @if(!empty($p['sport_class']))
                                                                            <span
                                                                                class="badge bg-light text-dark ms-2">{{ $p['sport_class'] }}</span>
                                                                        @endif
                                                                        <span
                                                                            class="text-muted small ms-2">leg {{ $p['leg'] ?? '—' }}</span>
                                                                        <span class="text-muted small ms-2">({{ $aid ?: '—' }})</span>
                                                                    </div>

                                                                    @if($aid !== '')
                                                                        <select name="athlete_match[{{ $aid }}]"
                                                                                class="form-select form-select-sm athlete-match mt-1"
                                                                                data-athlete="{{ $aid }}">
                                                                            <option
                                                                                value="auto" {{ (string)$selMatch === 'auto' ? 'selected' : '' }}>
                                                                                Auto (tmId/Service)
                                                                            </option>
                                                                            <option
                                                                                value="new" {{ (string)$selMatch === 'new' ? 'selected' : '' }}>
                                                                                Neu anlegen
                                                                            </option>

                                                                            @if(!empty($cands))
                                                                                <option disabled>──────────</option>
                                                                                @foreach($cands as $c)
                                                                                    @php
                                                                                        $cid = (int)($c['id'] ?? 0);
                                                                                        $score = (int)($c['score'] ?? 0);
                                                                                        $label = (string)($c['label'] ?? '');
                                                                                        $sel = ((string)$selMatch === (string)$cid) ? 'selected' : '';
                                                                                    @endphp
                                                                                    <option
                                                                                        value="{{ $cid }}" {{ $sel }}>
                                                                                        [{{ $score }}] {{ $label }}
                                                                                    </option>
                                                                                @endforeach
                                                                            @endif
                                                                        </select>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </td>

                                                        <td>
                                                            @if(!empty($blockers) || !empty($warnings))
                                                                <ul class="mb-0 ps-3 small">
                                                                    @foreach($blockers as $r)
                                                                        <li class="text-danger">{{ $r }}</li>
                                                                    @endforeach
                                                                    @foreach($warnings as $r)
                                                                        <li>{{ $r }}</li>
                                                                    @endforeach
                                                                </ul>
                                                            @else
                                                                <span class="text-muted small">—</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-muted">Keine Relays.</div>
                                @endforelse

                                <div class="mt-4 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Import Selected</button>
                                    <a class="btn btn-outline-secondary"
                                       href="{{ route('meets.show', $meet) }}">Cancel</a>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Import Selected</button>
                            <a class="btn btn-outline-secondary" href="{{ route('meets.show', $meet) }}">Cancel</a>
                        </div>
                    @endif

                </div>
            </div>
        </form>
    </div>

    <script>
        (() => {
            const qsa = (sel, root) => Array.from((root || document).querySelectorAll(sel));
            const qs = (sel, root) => (root || document).querySelector(sel);

            const onlySelected = qs('#onlySelected');

            const selectAllValid = qs('#selectAllValid');
            const unselectAllResults = qs('#unselectAllResults');
            const selectAllRelays = qs('#selectAllRelays');
            const unselectAllRelays = qs('#unselectAllRelays');

            function syncSelectByDataAttr(className, dataAttr) {
                qsa('.' + className).forEach(sel => {
                    sel.addEventListener('change', (e) => {
                        const id = e.target.getAttribute(dataAttr);
                        const val = e.target.value;
                        qsa('.' + className + '[' + dataAttr + '="' + id + '"]').forEach(s => {
                            if (s.value !== val) s.value = val;
                        });
                    });
                });
            }

            // Sync athlete dropdown across all occurrences (Results + Relays)
            syncSelectByDataAttr('athlete-match', 'data-athlete');

            // Sync club dropdown across Results+Relays blocks
            syncSelectByDataAttr('club-match', 'data-clubid');

            // --- Tree -> Import checkbox selection ---
            function setResultCheckboxesFor(predicate, checked) {
                qsa('tr[data-row-type="result"]').forEach(tr => {
                    if (!predicate(tr)) return;
                    const cb = qs('input.result-cb', tr);
                    if (!cb || cb.disabled) return;
                    cb.checked = checked;
                });
            }

            function setRelayCheckboxesFor(predicate, checked) {
                qsa('tr[data-row-type="relay"]').forEach(tr => {
                    if (!predicate(tr)) return;
                    const cb = qs('input.relay-cb', tr);
                    if (!cb || cb.disabled) return;
                    cb.checked = checked;
                });
            }

            function syncParents() {
                qsa('.tree-club').forEach(clubCb => {
                    const nation = clubCb.dataset.nation;
                    const club = clubCb.dataset.club;
                    const kids = qsa('.tree-athlete[data-nation="' + nation + '"][data-club="' + club + '"]');
                    if (kids.length) clubCb.checked = kids.every(c => c.checked);
                });

                qsa('.tree-nation').forEach(natCb => {
                    const nation = natCb.dataset.nation;
                    const clubs = qsa('.tree-club[data-nation="' + nation + '"]');
                    if (clubs.length) natCb.checked = clubs.every(c => c.checked);
                });
            }

            function syncTreeFromSelectedResults() {
                // if any result for athlete is selected -> athlete checked
                qsa('.tree-athlete').forEach(aCb => {
                    const aid = aCb.dataset.athlete;
                    aCb.checked = qsa('tr[data-row-type="result"][data-athlete="' + aid + '"] input.result-cb')
                        .some(cb => cb.checked);
                });
                syncParents();
            }

            // --- Visibility ---
            function applyVisibility() {
                const only = !!onlySelected?.checked;

                const selectedAthletes = new Set(qsa('.tree-athlete:checked').map(cb => cb.dataset.athlete));
                const selectedClubs = new Set(qsa('.tree-club:checked').map(cb => cb.dataset.club));
                const selectedNations = new Set(qsa('.tree-nation:checked').map(cb => cb.dataset.nation));

                const treeActive = selectedAthletes.size || selectedClubs.size || selectedNations.size;

                // Results rows
                qsa('tr[data-row-type="result"]').forEach(tr => {
                    const cb = qs('input.result-cb', tr);
                    const isChosen = cb ? cb.checked : false;

                    let visible = true;

                    // tree filter (optional)
                    if (treeActive) {
                        const a = tr.dataset.athlete || '';
                        const c = tr.dataset.club || '';
                        const n = tr.dataset.nation || '';

                        if (selectedAthletes.size) visible = selectedAthletes.has(a);
                        else if (selectedClubs.size) visible = selectedClubs.has(c);
                        else if (selectedNations.size) visible = selectedNations.has(n);
                    }

                    // only-selected filter
                    if (only) visible = visible && isChosen;

                    tr.hidden = !visible;
                });

                // Relay rows
                qsa('tr[data-row-type="relay"]').forEach(tr => {
                    const cb = qs('input.relay-cb', tr);
                    const isChosen = cb ? cb.checked : false;

                    let visible = true;

                    // tree filter: only nations/clubs (athlete selection shouldn't hide relays)
                    if (treeActive) {
                        const c = tr.dataset.club || '';
                        const n = tr.dataset.nation || '';

                        if (selectedClubs.size) visible = selectedClubs.has(c);
                        else if (selectedNations.size) visible = selectedNations.has(n);
                    }

                    if (only) visible = visible && isChosen;

                    tr.hidden = !visible;
                });

                // Hide club blocks if no visible rows
                qsa('.result-club-block').forEach(block => {
                    const anyVisible = qsa('tr[data-row-type="result"]', block).some(tr => !tr.hidden);
                    block.hidden = !anyVisible;
                });

                qsa('.relay-club-block').forEach(block => {
                    const anyVisible = qsa('tr[data-row-type="relay"]', block).some(tr => !tr.hidden);
                    block.hidden = !anyVisible;
                });
            }

            // --- Tree events: set import checkboxes ---
            qsa('.tree-athlete').forEach(cb => cb.addEventListener('change', (e) => {
                const aid = e.target.dataset.athlete;
                setResultCheckboxesFor(tr => (tr.dataset.athlete || '') === aid, e.target.checked);
                syncParents();
                applyVisibility();
            }));

            qsa('.tree-club').forEach(cb => cb.addEventListener('change', (e) => {
                const club = e.target.dataset.club;
                const nation = e.target.dataset.nation;
                const checked = e.target.checked;

                // toggle athletes under club
                qsa('.tree-athlete[data-nation="' + nation + '"][data-club="' + club + '"]').forEach(x => x.checked = checked);

                // toggle results under club
                setResultCheckboxesFor(tr => (tr.dataset.club || '') === club, checked);

                // toggle relays under club
                setRelayCheckboxesFor(tr => (tr.dataset.club || '') === club, checked);

                syncParents();
                applyVisibility();
            }));

            qsa('.tree-nation').forEach(cb => cb.addEventListener('change', (e) => {
                const nation = e.target.dataset.nation;
                const checked = e.target.checked;

                qsa('.tree-club[data-nation="' + nation + '"]').forEach(x => x.checked = checked);
                qsa('.tree-athlete[data-nation="' + nation + '"]').forEach(x => x.checked = checked);

                // toggle results under nation
                setResultCheckboxesFor(tr => (tr.dataset.nation || '') === nation, checked);

                // toggle relays under nation
                setRelayCheckboxesFor(tr => (tr.dataset.nation || '') === nation, checked);

                syncParents();
                applyVisibility();
            }));

            // --- Manual checkbox changes update tree + visibility ---
            qsa('input.result-cb').forEach(cb => cb.addEventListener('change', () => {
                syncTreeFromSelectedResults();
                applyVisibility();
            }));
            qsa('input.relay-cb').forEach(cb => cb.addEventListener('change', () => {
                applyVisibility();
            }));

            onlySelected?.addEventListener('change', () => applyVisibility());

            // --- Buttons ---
            selectAllValid?.addEventListener('click', () => {
                qsa('input.result-cb').forEach(cb => {
                    if (!cb.disabled) cb.checked = true;
                });
                syncTreeFromSelectedResults();
                applyVisibility();
            });

            unselectAllResults?.addEventListener('click', () => {
                qsa('input.result-cb').forEach(cb => {
                    if (!cb.disabled) cb.checked = false;
                });
                syncTreeFromSelectedResults();
                applyVisibility();
            });

            selectAllRelays?.addEventListener('click', () => {
                qsa('input.relay-cb').forEach(cb => {
                    if (!cb.disabled) cb.checked = true;
                });
                applyVisibility();
            });

            unselectAllRelays?.addEventListener('click', () => {
                qsa('input.relay-cb').forEach(cb => {
                    if (!cb.disabled) cb.checked = false;
                });
                applyVisibility();
            });

            // init
            syncTreeFromSelectedResults();
            applyVisibility();
        })();
    </script>
@endsection
