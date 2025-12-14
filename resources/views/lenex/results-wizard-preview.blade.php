@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>LENEX Import Wizard – Preview</h1>

        <div class="mb-3">
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

            <div class="d-flex gap-2 align-items-center mb-3">
                @if($doResults)
                    <button type="button" id="toggleAllResults" class="btn btn-sm btn-outline-secondary">
                        Toggle all valid Athlete Results
                    </button>
                @endif

                @if($doRelays)
                    <button type="button" id="toggleAllRelays" class="btn btn-sm btn-outline-secondary">
                        Toggle all valid Relays
                    </button>
                @endif

                <a class="btn btn-sm btn-link" href="{{ route('meets.lenex.results-wizard.form', $meet) }}">New file</a>
                <a class="btn btn-sm btn-link" href="{{ route('meets.show', $meet) }}">Back to Meet</a>
            </div>

            {{-- ===================== Athlete Results ===================== --}}
            @if($doResults)
                <h2 class="mt-4">Athlete Results</h2>

                @php($clubs = $resultsData['clubs'] ?? [])
                @forelse($clubs as $club)
                    <h5 class="mt-3">
                        {{ $club['club_name'] ?: '—' }}
                        <small class="text-muted">{{ $club['nation'] ?? '' }}</small>
                    </h5>

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
                                @php($invalid = (bool)($row['invalid'] ?? false))
                                <tr class="{{ $invalid ? 'table-danger' : '' }}">
                                    <td>
                                        <input class="form-check-input result-cb"
                                               type="checkbox"
                                               name="selected_results[]"
                                               value="{{ $row['result_key'] }}"
                                            {{ $invalid ? 'disabled' : '' }}>
                                    </td>

                                    <td>
                                        <strong>{{ $row['event_label'] ?? '—' }}</strong>
                                        <div class="text-muted small">
                                            LENEX eventid: {{ $row['lenex_eventid'] ?? '—' }}
                                            @if(!empty($row['swimstyle_id']))
                                                · swimstyle_id: {{ $row['swimstyle_id'] }}
                                            @endif
                                        </div>
                                    </td>

                                    <td>
                                        {{ $row['last_name'] ?? '' }}, {{ $row['first_name'] ?? '' }}
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
                                                <div class="small">
                                                    {{ $s['distance'] ?? '—' }}m: {{ $s['time_fmt'] ?? '—' }}
                                                </div>
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

            {{-- ===================== Relays ===================== --}}
            @if($doRelays)
                <h2 class="mt-5">Relays</h2>

                @php($clubs = $relaysData['clubs'] ?? [])
                @php($byNation = collect($clubs)->groupBy('nation'))

                @forelse($byNation as $nationCode => $nationClubs)
                    <h5 class="mt-3">{{ $nationCode ?: '—' }}</h5>

                    @foreach($nationClubs as $club)
                        <h6 class="mt-2">{{ $club['club_name'] ?: '—' }}</h6>

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
                                    @php($invalid = (bool)($row['invalid'] ?? false))
                                    <tr class="{{ $invalid ? 'table-danger' : '' }}">
                                        <td>
                                            <input class="form-check-input relay-cb"
                                                   type="checkbox"
                                                   name="selected_relays[]"
                                                   value="{{ $row['lenex_resultid'] ?: $row['result_id'] }}"
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
                                            <div class="text-muted small">
                                                LENEX eventid: {{ $row['lenex_eventid'] ?? '—' }}
                                            </div>
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
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('toggleAllResults')?.addEventListener('click', () => {
            const boxes = Array.from(document.querySelectorAll('.result-cb')).filter(cb => !cb.disabled);
            const anyUnchecked = boxes.some(cb => !cb.checked);
            boxes.forEach(cb => cb.checked = anyUnchecked);
        });

        document.getElementById('toggleAllRelays')?.addEventListener('click', () => {
            const boxes = Array.from(document.querySelectorAll('.relay-cb')).filter(cb => !cb.disabled);
            const anyUnchecked = boxes.some(cb => !cb.checked);
            boxes.forEach(cb => cb.checked = anyUnchecked);
        });
    </script>
@endpush
