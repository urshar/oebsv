@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Klassifikation bearbeiten – {{ $athlete->lastName }} {{ $athlete->firstName }}</h1>

        <div class="mb-3">
            <a href="{{ route('athletes.show', $athlete) }}" class="btn btn-sm btn-secondary">
                &laquo; zurück zum Athleten
            </a>
            <a href="{{ route('athletes.classifications.index', $athlete) }}" class="btn btn-sm btn-outline-secondary">
                Historie
            </a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">Bitte prüfen Sie die Eingaben.</div>
        @endif

        <form action="{{ route('classifications.update', $classification) }}" method="POST">
            @csrf
            @method('PUT')

            @include('athletes.classifications._form', [
                'athlete'              => $athlete,
                'classification'       => $classification,
                'technicalClassifiers' => $technicalClassifiers,
                'medicalClassifiers'   => $medicalClassifiers,
            ])

            <button type="submit" class="btn btn-primary">
                Änderungen speichern
            </button>
        </form>
    </div>
@endsection
