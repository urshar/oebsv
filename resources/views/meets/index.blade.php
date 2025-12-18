@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Para Meetings</h1>

        @if(session('status'))
            <div class="alert alert-success mt-2">
                {{ session('status') }}
            </div>
        @endif

        @if($meets->isEmpty())
            <p>Derzeit sind keine Meetings vorhanden.</p>
        @else
            <table class="table table-striped mt-3">
                <thead>
                <tr>
                    <th class="text-center" style="width:70px;">IER</th>
                    <th>Name</th>
                    <th>Ort</th>
                    <th>Nation</th>
                    <th>Von</th>
                    <th>Bis</th>
                    <th class="text-end">Aktionen</th>
                </tr>
                </thead>
                <tbody>
                @foreach($meets as $meet)
                    @php
                        $hasStructure = ($meet->sessions_count ?? 0) > 0;
                        $hasEntries   = ($meet->entries_count ?? 0) > 0;
                        $hasResults   = ($meet->results_count ?? 0) > 0;

                        // Default: nichts
                        $code  = '—';
                        $class = 'bg-secondary';

                        // Deine Logik
                        if ($hasResults && $hasStructure) {
                            $code  = 'R';
                            $class = 'bg-success';
                        } elseif ($hasStructure && $hasEntries && !$hasResults) {
                            $code  = 'E';
                            $class = 'bg-warning text-dark';
                        } elseif ($hasStructure && !$hasEntries && !$hasResults) {
                            $code  = 'I';
                            $class = 'bg-info text-dark';
                        }
                    @endphp

                    <tr>
                        <td class="text-center">
                            <span class="badge rounded-pill {{ $class }}">
                                {{ $code }}
                            </span>
                        </td>
                        <td>{{ $meet->name }}</td>
                        <td>{{ $meet->city }}</td>
                        <td>{{ optional($meet->nation)->ioc }}</td>
                        <td>{{ optional($meet->from_date)?->format('d.m.Y') }}</td>
                        <td>{{ optional($meet->to_date)?->format('d.m.Y') }}</td>
                        <td class="text-end">
                            <a href="{{ route('meets.show', $meet) }}" class="btn btn-sm btn-secondary">
                                Details
                            </a>
                            <a href="{{ route('meets.edit', $meet) }}" class="btn btn-sm btn-primary">
                                Bearbeiten
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
