@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>New para classifier</h1>

        <a href="{{ route('classifiers.index') }}" class="btn btn-sm btn-secondary mb-3">
            &laquo; Back to list
        </a>

        <form action="{{ route('classifiers.store') }}" method="POST">
            @csrf

            @include('paraclassifiers._form', [
                'classifier' => $classifier,
                'nations'    => $nations,
            ])

            <button type="submit" class="btn btn-primary">
                Save
            </button>
        </form>
    </div>
@endsection
