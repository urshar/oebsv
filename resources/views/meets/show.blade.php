@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>{{ $meet->name }}</h1>

        @if(session('status'))
            <div class="alert alert-success mt-2 mb-3">
                {{ session('status') }}
            </div>
        @endif

        <div class="mb-3">
            <a href="{{ route('meets.edit', $meet) }}" class="btn btn-secondary btn-sm">
                Meeting bearbeiten
            </a>
            <a href="{{ route('meets.sessions.create', $meet) }}" class="btn btn-primary btn-sm">
                Session hinzufügen
            </a>
            <a href="{{ route('lenex.entries.form', $meet) }}" class="btn btn-sm btn-outline-primary">
                Entries importieren
            </a>
            <a href="{{ route('meets.athletes.index', $meet) }}" class="btn btn-outline-primary btn-sm">
                Athleten & Meldungen
            </a>

            {{-- Alle Entries löschen --}}
            <form action="{{ route('meets.entries.destroy', $meet) }}"
                  method="POST"
                  class="d-inline"
                  onsubmit="return confirm('Wirklich ALLE Meldungen (Entries) für dieses Meeting löschen?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    Alle Meldungen löschen
                </button>
            </form>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Allgemein</h5>
                <dl class="row mb-0">
                    <dt class="col-sm-3">Ort</dt>
                    <dd class="col-sm-9">{{ $meet->city }}</dd>

                    <dt class="col-sm-3">Nation</dt>
                    <dd class="col-sm-9">
                        {{ $meet->nation?->ioc ?? $meet->nation?->nameEn }}
                    </dd>

                    <dt class="col-sm-3">Zeitraum</dt>
                    <dd class="col-sm-9">
                        {{ optional($meet->from_date)->format('d.m.Y') }}
                        –
                        {{ optional($meet->to_date)->format('d.m.Y') }}
                    </dd>

                    <dt class="col-sm-3">Meldebeginn / -schluss</dt>
                    <dd class="col-sm-9">
                        {{ optional($meet->entry_start_date)->format('d.m.Y') }}
                        /
                        {{ optional($meet->entry_deadline)->format('d.m.Y') }}
                    </dd>
                </dl>
            </div>
        </div>

        @forelse($meet->sessions as $session)
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Session {{ $session->number }}</strong>
                        – {{ optional($session->date)->format('d.m.Y') }}
                        @if($session->start_time)
                            – {{ \Illuminate\Support\Carbon::parse($session->start_time)->format('H:i') }} Uhr
                        @endif
                    </div>
                    <div>
                        <a href="{{ route('sessions.edit', $session) }}" class="btn btn-sm btn-secondary">
                            Session bearbeiten
                        </a>
                        <a href="{{ route('sessions.events.create', $session) }}" class="btn btn-sm btn-primary">
                            Event hinzufügen
                        </a>
                    </div>
                </div>

                <div class="card-body p-0">
                    @if($session->events->isEmpty())
                        <p class="p-3 mb-0">Keine Events in dieser Session.</p>
                    @else
                        <table class="table table-sm mb-0">
                            <thead>
                            <tr>
                                <th>Nr.</th>
                                <th>Wettbewerb</th>
                                <th>Runde</th>
                                <th>Agegroups</th>
                                <th class="text-end">Aktionen</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($session->events as $event)
                                <tr>
                                    <td>{{ $event->number }}</td>
                                    <td>
                                        @php $s = $event->swimstyle; @endphp
                                        @if($s)
                                            {{ $s->distance }}m {{ $s->stroke }}@if($s->is_relay) Staffel @endif
                                        @else
                                            –
                                        @endif
                                    </td>
                                    <td>{{ $event->round }}</td>
                                    <td>
                                        @forelse($event->agegroups as $age)
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <div>
                                                    {{ $age->name }}
                                                    ({{ $age->age_min }}–{{ $age->age_max }},
                                                    {{ $age->gender ?: 'mixed' }})
                                                </div>
                                                <div class="ms-2">
                                                    <a href="{{ route('agegroups.edit', $age) }}"
                                                       class="btn btn-xs btn-outline-secondary btn-sm">
                                                        Edit
                                                    </a>
                                                    <form action="{{ route('agegroups.destroy', $age) }}"
                                                          method="POST"
                                                          class="d-inline"
                                                          onsubmit="return confirm('Agegroup wirklich löschen?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-xs btn-outline-danger btn-sm">X</button>
                                                    </form>
                                                </div>
                                            </div>
                                        @empty
                                            <span class="text-muted">keine Agegroups</span>
                                        @endforelse
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('events.edit', $event) }}" class="btn btn-sm btn-secondary">
                                            Event bearbeiten
                                        </a>

                                        {{-- HIER: Neuer Button für Agegroup anlegen --}}
                                        <a href="{{ route('events.agegroups.create', $event) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            Agegroup hinzufügen
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @empty
            <p>Keine Sessions vorhanden.</p>
        @endforelse
    </div>
@endsection
