<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', config('app.name', 'Para Swim Admin'))</title>

    {{-- Bootstrap 5 CSS --}}
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >

    {{-- wenn du Vite nicht nutzt, diese Zeile einfach löschen --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body>
<header class="bg-dark text-white mb-4">
    <div class="container d-flex justify-content-between align-items-center py-2">
        <div class="fw-bold">
            <a href="{{ url('/') }}" class="text-white text-decoration-none">
                {{ config('app.name', 'Para Swim Admin') }}
            </a>
        </div>

        @php $navMeet = request()->route('meet'); @endphp

        <nav class="d-flex gap-3">
            <a href="{{ route('nations.index') }}"
               class="text-white text-decoration-none {{ request()->is('nations*') ? 'fw-bold text-decoration-underline' : '' }}">
                Nationen
            </a>

            <a href="{{ route('meets.index') }}"
               class="text-white text-decoration-none {{ request()->is('meets*') ? 'fw-bold text-decoration-underline' : '' }}">
                Meets
            </a>

            @php
                $lenexActive = request()->routeIs('lenex.*') || request()->routeIs('meets.lenex.*');
            @endphp

            <div class="dropdown">
                <a class="text-white text-decoration-none dropdown-toggle {{ $lenexActive ? 'fw-bold text-decoration-underline' : '' }}"
                   href="#"
                   role="button"
                   data-bs-toggle="dropdown"
                   aria-expanded="false">
                    LENEX Import
                </a>

                <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                    {{-- Meet + Struktur (ohne Meet-Kontext) --}}
                    <li>
                        <a class="dropdown-item" href="{{ route('lenex.meet-wizard.form') }}">
                            Meet + Struktur
                        </a>
                    </li>

                    <li>
                        <hr class="dropdown-divider">
                    </li>

                    {{-- Entries/Results (nur wenn ein Meet im Kontext ist) --}}
                    @if($navMeet)
                        <li>
                            <a class="dropdown-item" href="{{ route('meets.lenex.entries-wizard.form', $navMeet) }}">
                                Entries
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('meets.lenex.results-wizard.form', $navMeet) }}">
                                Results
                            </a>
                        </li>
                    @else
                        <li><span class="dropdown-item-text text-secondary">Entries (Meet auswählen)</span></li>
                        <li><span class="dropdown-item-text text-secondary">Results (Meet auswählen)</span></li>
                    @endif
                </ul>
            </div>

            <a href="{{ route('athletes.index') }}"
               class="text-white text-decoration-none {{ request()->is('athletes*') ? 'fw-bold text-decoration-underline' : '' }}">
                Athletes
            </a>

            <a href="{{ route('para-records.index') }}"
               class="text-white text-decoration-none {{ request()->is('para-records*') ? 'fw-bold text-decoration-underline' : '' }}">
                Records
            </a>
        </nav>
    </div>
</header>

<main>
    @yield('content')
</main>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"
></script>

@stack('scripts')
</body>
</html>
