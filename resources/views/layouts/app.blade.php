<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', config('app.name', 'Para Swim Admin'))</title>

    {{-- Bootstrap 5 CSS (for basic styling; optional but nice) --}}
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
{{-- Simple header, no collapse, no JS required --}}
<header class="bg-dark text-white mb-4">
    <div class="container d-flex justify-content-between align-items-center py-2">
        <div class="fw-bold">
            <a href="{{ url('/') }}" class="text-white text-decoration-none">
                {{ config('app.name', 'Para Swim Admin') }}
            </a>
        </div>

        <nav class="d-flex gap-3">
            <a href="{{ route('continents.index') }}"
               class="text-white text-decoration-none {{ request()->is('continents*') ? 'fw-bold text-decoration-underline' : '' }}">
                Kontinente
            </a>
            <a href="{{ route('nations.index') }}"
               class="text-white text-decoration-none {{ request()->is('nations*') ? 'fw-bold text-decoration-underline' : '' }}">
                Nationen
            </a>
            <a href="{{ route('lenex.upload.form') }}"
               class="text-white text-decoration-none {{ request()->is('lenex*') ? 'fw-bold text-decoration-underline' : '' }}">
                LENEX Import
            </a>
            <a href="{{ route('meets.index') }}"
               class="text-white text-decoration-none {{ request()->is('meets*') ? 'fw-bold text-decoration-underline' : '' }}">
                Meets
            </a>
            <a href="{{ route('athletes.index') }}"
               class="text-white text-decoration-none {{ request()->is('athletes*') ? 'fw-bold text-decoration-underline' : '' }}">
                Athletes
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
