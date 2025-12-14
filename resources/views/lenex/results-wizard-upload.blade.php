@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>LENEX Import Wizard</h1>

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

        <form method="POST"
              action="{{ route('meets.lenex.results-wizard.preview', $meet) }}"
              enctype="multipart/form-data">
            @csrf

            <div class="mb-3">
                <label class="form-label">LENEX File (.lxf/.zip/.lef/.xml)</label>
                <input type="file" class="form-control" name="lenex_file" required>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="do_results" value="1" id="do_results" checked>
                    <label class="form-check-label" for="do_results">Import Athlete Results (with Splits)</label>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="do_relays" value="1" id="do_relays" checked>
                    <label class="form-check-label" for="do_relays">Import Relays</label>
                </div>
            </div>

            <button class="btn btn-primary">Preview</button>
            <a class="btn btn-link" href="{{ route('meets.show', $meet) }}">Back to Meet</a>
        </form>
    </div>
@endsection
