@extends('layouts.app')

@section('title', 'Österreichische Rekorde')

@section('content')
    <div class="container mt-4">
        <h1 class="mb-4">Österreichische Rekorde</h1>

        @php
            function formatSwimTime(?int $ms): ?string {
                if ($ms === null) {
                    return null;
                }
                $totalSeconds = intdiv($ms, 1000);
                $msRemainder  = $ms % 1000;

                $minutes = intdiv($totalSeconds, 60);
                $seconds = $totalSeconds % 60;
                $centi   = intdiv($msRemainder, 10); // Hundertstel

                return sprintf('%d:%02d.%02d', $minutes, $seconds, $centi);
            }

            $baseParams = function(array $extra = []) use ($selectedClass, $selectedGender, $selectedCourse, $selectedCategory) {
                return array_merge([
                    'class'  => $selectedClass,
                    'gender' => $selectedGender,
                    'course' => $selectedCourse,
                    'cat'    => $selectedCategory,
                ], $extra);
            };
        @endphp

        {{-- Klassen-Buttons --}}
        <div class="mb-3">
            <div class="btn-group" role="group" aria-label="Sportklassen">
                @foreach($classes as $cls)
                    @php $num = str_pad($cls, 2, '0', STR_PAD_LEFT); @endphp
                    <a href="{{ route('para-records.index', $baseParams(['class' => $cls])) }}"
                       class="btn btn-sm {{ $selectedClass === $cls ? 'btn-primary' : 'btn-outline-secondary' }}">
                        {{ $num }}
                    </a>
                @endforeach
            </div>

            {{-- Gender --}}
            <div class="btn-group ms-3" role="group" aria-label="Geschlecht">
                <a href="{{ route('para-records.index', $baseParams(['gender' => 'M'])) }}"
                   class="btn btn-sm {{ $selectedGender === 'M' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    ♂
                </a>
                <a href="{{ route('para-records.index', $baseParams(['gender' => 'F'])) }}"
                   class="btn btn-sm {{ $selectedGender === 'F' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    ♀
                </a>
            </div>

            {{-- Course --}}
            <div class="btn-group ms-3" role="group" aria-label="Bahnlänge">
                <a href="{{ route('para-records.index', $baseParams(['course' => 'SCM'])) }}"
                   class="btn btn-sm {{ $selectedCourse === 'SCM' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    SC
                </a>
                <a href="{{ route('para-records.index', $baseParams(['course' => 'LCM'])) }}"
                   class="btn btn-sm {{ $selectedCourse === 'LCM' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    LC
                </a>
            </div>

            {{-- Jugend / Offen --}}
            <div class="btn-group ms-3" role="group">
                <a href="{{ route('para-records.index', $baseParams(['cat' => 'OP'])) }}"
                   class="btn btn-sm {{ $selectedCategory === 'OP' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    OP
                </a>
                <a href="{{ route('para-records.index', $baseParams(['cat' => 'JG'])) }}"
                   class="btn btn-sm {{ $selectedCategory === 'JG' ? 'btn-primary' : 'btn-outline-secondary' }}">
                    JG
                </a>
            </div>

            {{-- Print-Button --}}
            <div class="btn-group ms-3" role="group">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                    🖨
                </button>
            </div>

            {{-- Import --}}
            <div class="btn-group ms-3" role="group">
                <a href="{{ route('para-records.import.create') }}"
                   class="btn btn-sm btn-success">
                    Import
                </a>
            </div>
        </div>

        {{-- Tabelle --}}
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead class="table-light">
                <tr>
                    <th style="width: 90px;">Stroke</th>
                    <th>Art</th>
                    <th style="width: 110px;">Swimtime</th>
                    <th>Lastname</th>
                    <th>Firstname</th>
                    <th style="width: 70px;">YoB</th>
                    <th>Club</th>
                    <th>Meetplace</th>
                    <th style="width: 130px;">Recorddate</th>
                </tr>
                </thead>
                <tbody>
                @forelse($records as $record)
                    <tr>
                        <td>{{ $record->distance }}m</td>
                        <td>{{ $strokeLabels[$record->stroke] ?? $record->stroke }}</td>
                        <td>{{ formatSwimTime($record->swimtime_ms) }}</td>
                        <td>{{ $record->holder_lastname }}</td>
                        <td>{{ $record->holder_firstname }}</td>
                        <td>{{ $record->holder_year_of_birth }}</td>
                        <td>{{ optional($record->club)->nameDe }}</td>
                        <td>{{ $record->meet_name }}</td>
                        <td>{{ optional($record->swum_at)->format('d.m.Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted">
                            Keine Rekorde für diese Auswahl vorhanden.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
