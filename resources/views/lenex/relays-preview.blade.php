@php
    use App\Support\SwimTime;
@endphp

@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Staffeln importieren – Preview</h1>

        <div class="mb-3">
            <div><strong>Meeting:</strong> {{ $meet->name }}</div>
            <div><strong>Ort:</strong> {{ $meet->city }}</div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('meets.lenex.relays.import', $meet) }}">
            @csrf

            <input type="hidden" name="lenex_file_path" value="{{ $lenexFilePath }}">

            <div class="d-flex gap-2 align-items-center mb-3">
                <button type="button" id="toggleAll" class="btn btn-sm btn-outline-secondary">Alle umschalten</button>
                <a class="btn btn-sm btn-link" href="{{ route('meets.lenex.relays.form', $meet) }}">Neue Datei</a>
            </div>

            @php $byNation = collect($clubs)->groupBy('nation'); @endphp

            @forelse($byNation as $nationCode => $nationClubs)
                <h3 class="mt-4">{{ $nationCode ?: 'ohne Nation' }}</h3>

                @foreach($nationClubs as $club)
                    <h4 class="mt-3">{{ $club['club_name'] ?: 'ohne Vereinsname' }}</h4>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                            <tr>
                                <th style="width:80px;">Import</th>
                                <th>LENEX Event</th>
                                <th>Relay</th>
                                <th>Zeit</th>
                                <th>Teilnehmer</th>
                                <th>Hinweise</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($club['relay_rows'] as $row)
                                @php
                                    $invalid = $row['invalid'];
                                @endphp

                                <tr class="{{ $invalid ? 'table-danger' : '' }}">
                                    <td>
                                        <input
                                            class="form-check-input relay-cb"
                                            type="checkbox"
                                            name="selected_relays[]"
                                            value="{{ $row['lenex_resultid'] ?: $row['result_id'] }}"
                                            {{ $invalid ? 'disabled' : '' }}
                                        >
                                    </td>

                                    <td>
                                        <div>
                                            <strong>{{ $row['relay_event_label'] }}</strong>
                                            @if(!empty($row['relay_sportclass']))
                                                <span
                                                    class="badge bg-secondary ms-2">{{ $row['relay_sportclass'] }}</span>
                                            @endif

                                            @if(!empty($row['agegroup_name']))
                                                <div class="text-muted small">{{ $row['agegroup_name'] }}</div>
                                            @endif

                                            <div class="text-muted small">
                                                LENEX eventid: {{ $row['lenex_eventid'] ?? '—' }}
                                                @if(!empty($row['swimstyle_id']))
                                                    · swimstyle_id: {{ $row['swimstyle_id'] }}
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        <div>
                                            <strong>#{{ $row['relay_number'] ?: '—' }}</strong> {{ $row['relay_gender'] ?: '' }}
                                        </div>
                                    </td>

                                    <td>
                                        {{ $row['swimtime_ms'] !== null ? SwimTime::format($row['swimtime_ms']) : '—' }}
                                        <div class="text-muted small">{{ $row['swimtime'] ?: '' }}</div>
                                    </td>


                                    <td>
                                        @foreach($row['positions'] as $p)
                                            <div>
                                                <strong>{{ $p['leg'] }}.</strong>
                                                {{ $p['last_name'] }}, {{ $p['first_name'] }}
                                                <span
                                                    class="{{ (!$p['in_lenex_club'] || !$p['exists_in_db']) ? 'text-danger' : 'text-success' }}">
                                                    ({{ $p['lenex_athlete_id'] }})
                                                </span>
                                                @if($p['db_athlete_id'])
                                                    <small class="text-muted">DB#{{ $p['db_athlete_id'] }}</small>
                                                @endif

                                                @php $sc = $p['sport_class'] ?? null; @endphp
                                                <span
                                                    class="badge bg-light text-dark border ms-2 {{ $sc === null ? 'text-danger' : '' }}">
                                                    SK {{ $sc ?? '—' }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </td>

                                    <td>
                                        @if($invalid)
                                            <ul class="mb-0">
                                                @foreach($row['invalid_reasons'] as $r)
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
                <div class="alert alert-info">In der Datei wurden keine Staffeln gefunden.</div>
            @endforelse

            <button class="btn btn-primary mt-3">Auswahl importieren</button>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('toggleAll')?.addEventListener('click', () => {
            const boxes = Array.from(document.querySelectorAll('.relay-cb')).filter(cb => !cb.disabled);
            const anyUnchecked = boxes.some(cb => !cb.checked);
            boxes.forEach(cb => cb.checked = anyUnchecked);
        });
    </script>
@endpush
