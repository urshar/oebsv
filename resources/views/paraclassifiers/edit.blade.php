@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Edit para classifier</h1>

        <a href="{{ route('classifiers.index') }}" class="btn btn-sm btn-secondary mb-3">
            &laquo; Back to list
        </a>

        <form action="{{ route('classifiers.update', $classifier) }}" method="POST">
            @csrf
            @method('PUT')

            @include('paraclassifiers._form', [
                'classifier' => $classifier,
                'nations'    => $nations,
            ])

            <button type="submit" class="btn btn-primary">
                Update
            </button>
        </form>
    </div>
@endsection
