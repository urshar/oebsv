@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Para classifiers</h1>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <div class="mb-3">
            <a href="{{ route('classifiers.create') }}" class="btn btn-primary btn-sm">
                + New classifier
            </a>
        </div>

        @if($classifiers->isEmpty())
            <p>No classifiers found.</p>
        @else
            <table class="table table-striped align-middle">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>WPS ID</th>
                    <th>Nation</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach($classifiers as $classifier)
                    <tr>
                        <td>{{ $classifier->fullName }}</td>
                        <td>{{ $classifier->type }}</td>
                        <td>{{ $classifier->wps_id }}</td>
                        <td>{{ optional($classifier->nation)->display_name  ?? '-' }}</td>
                        <td>{{ $classifier->email }}</td>
                        <td>{{ $classifier->phone }}</td>
                        <td class="text-end">
                            <a href="{{ route('classifiers.edit', $classifier) }}" class="btn btn-sm btn-outline-primary">
                                Edit
                            </a>

                            <form action="{{ route('classifiers.destroy', $classifier) }}"
                                  method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this classifier?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            {{ $classifiers->links() }}
        @endif
    </div>
@endsection
