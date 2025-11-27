@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Agegroup hinzufügen – Event {{ $event->number }}</h1>

        <p>
            @php $s = $event->swimstyle; @endphp
            @if($s)
                {{ $s->distance }}m {{ $s->stroke }}@if($s->is_relay) Staffel @endif
            @endif
        </p>

        <form action="{{ route('events.agegroups.store', $event) }}" method="POST" class="mt-3">
            @include('agegroups._form')
        </form>
    </div>
@endsection
