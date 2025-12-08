@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Neue Vereine und Athleten bestätigen</h1>

        <p>
            Veranstaltung: <strong>{{ $meet->name }}</strong><br>
            Ort: {{ $meet->city }}<br>
            Datum:
            {{ optional($meet->from_date)?->format('d.m.Y') }}
            @if($meet->to_date)
                – {{ optional($meet->to_date)?->format('d.m.Y') }}
            @endif
        </p>

        <div class="alert alert-warning">
            Die folgenden Vereine und Athleten sind im System noch nicht vorhanden
            (oder wurden nicht eindeutig erkannt) und würden neu angelegt werden.
            Sie können diese neuen Datensätze bestehenden Vereinen/Schwimmern zuordnen
            oder die Anlage als neue Datensätze bestätigen.
        </div>

        {{-- WICHTIG: neue Route aus web.php verwenden --}}
        <form method="POST" action="{{ route('meets.lenex.results.import', $meet) }}">
            @csrf

            <input type="hidden" name="lenex_file_path" value="{{ $lenexFilePath }}">
            <input type="hidden" name="confirmation_step" value="1">

            @foreach($selectedAthletes as $id)
                <input type="hidden" name="selected_athletes[]" value="{{ $id }}">
            @endforeach

            <div class="mb-2">
                <label class="form-check-label">
                    <input type="checkbox" id="select-all-new" class="form-check-input" checked>
                    Alle neuen Vereine/Athleten auswählen / abwählen
                </label>
            </div>

            {{-- Neue Vereine --}}
            @if(!empty($newClubs))
                <h3>Neue Vereine</h3>
                <ul class="list-unstyled">
                    @foreach($newClubs as $club)
                        @php
                            $clubKey = $club['clubKey'];
                            $clubId  = 'club-' . $clubKey;
                        @endphp
                        <li class="mb-2">
                            <div class="form-check">
                                <input type="checkbox"
                                       class="form-check-input club-checkbox"
                                       id="{{ $clubId }}"
                                       data-club-key="{{ $clubKey }}"
                                       checked>
                                <label class="form-check-label fw-semibold" for="{{ $clubId }}">
                                    {{ $club['nation'] ?: '–' }} – {{ $club['name'] ?: '–' }}
                                </label>

                                <select name="club_mapping[{{ $clubKey }}]"
                                        class="form-select form-select-sm d-inline-block w-auto ms-2">
                                    <option value="">als neuen Verein anlegen</option>
                                    @foreach($existingClubs as $existingClub)
                                        <option value="{{ $existingClub->id }}">
                                            {{ $existingClub->nameDe }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Neue Athleten --}}
            @if(!empty($newAthletes))
                <h3>Neue Athleten</h3>
                <table class="table table-sm table-bordered">
                    <thead>
                    <tr>
                        <th></th>
                        <th>Nation</th>
                        <th>Verein</th>
                        <th>Nachname</th>
                        <th>Vorname</th>
                        <th>Geburtsdatum</th>
                        <th>Bestehender Athlet (optional)</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($newAthletes as $ath)
                        @php
                            $lenexId = $ath['lenexAthleteId'];
                            $clubKey = $ath['clubKey'];
                            $rowId   = 'athlete-'.$lenexId;
                        @endphp
                        <tr>
                            <td>
                                <input type="checkbox"
                                       class="form-check-input athlete-checkbox"
                                       id="{{ $rowId }}"
                                       name="confirmed_new_athletes[]"
                                       value="{{ $lenexId }}"
                                       data-club-key="{{ $clubKey }}"
                                       checked>
                            </td>
                            <td>{{ $ath['nation'] ?: '–' }}</td>
                            <td>{{ $ath['clubName'] ?: '–' }}</td>
                            <td>{{ $ath['lastName'] ?: '–' }}</td>
                            <td>{{ $ath['firstName'] ?: '–' }}</td>
                            <td>{{ $ath['birthdate'] ?: '–' }}</td>
                            <td style="min-width: 250px;">
                                <select name="athlete_mapping[{{ $lenexId }}]"
                                        class="form-select form-select-sm">
                                    <option value="">als neuen Athleten anlegen</option>
                                    @foreach($ath['candidates'] as $candidate)
                                        <option value="{{ $candidate->id }}">
                                            {{ $candidate->lastName }}, {{ $candidate->firstName }}
                                            @if($candidate->birthdate)
                                                ({{ $candidate->birthdate->format('d.m.Y') }})
                                            @endif
                                            @if($candidate->club)
                                                – {{ $candidate->club->nameDe }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    Auswahl übernehmen und Ergebnisse importieren
                </button>

                {{-- Zurück: auf das Upload-Formular (GET), nicht auf die POST-Preview-Route --}}
                <a href="{{ route('meets.lenex.results.form', $meet) }}" class="btn btn-secondary">
                    Zurück zum Datei-Upload
                </a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAll = document.getElementById('select-all-new');
            const clubCheckboxes = document.querySelectorAll('.club-checkbox');
            const athleteCheckboxes = document.querySelectorAll('.athlete-checkbox');

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    const checked = selectAll.checked;
                    clubCheckboxes.forEach(cb => cb.checked = checked);
                    athleteCheckboxes.forEach(cb => cb.checked = checked);
                });
            }

            // Club-Checkbox → alle Athleten dieses Clubs ein-/ausschalten
            clubCheckboxes.forEach(function (clubCb) {
                clubCb.addEventListener('change', function () {
                    const clubKey = clubCb.getAttribute('data-club-key');
                    const checked = clubCb.checked;

                    document.querySelectorAll('.athlete-checkbox[data-club-key="' + clubKey + '"]')
                        .forEach(cb => cb.checked = checked);
                });
            });
        });
    </script>
@endpush
