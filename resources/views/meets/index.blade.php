@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Meetings</h1>

        @if(session('status'))
            <div class="alert alert-success mt-2 mb-3">
                {{ session('status') }}
            </div>
        @endif

        @if($meets->isEmpty())
            <p>Keine Meetings vorhanden.</p>
        @else
            <table class="table table-bordered table-striped">
                <thead>
                <tr>
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
                    <tr>
                        <td>{{ $meet->name }}</td>
                        <td>{{ $meet->city }}</td>
                        <td>{{ $meet->nation?->ioc ?? $meet->nation?->nameEn }}</td>
                        <td>{{ optional($meet->from_date)->format('d.m.Y') }}</td>
                        <td>{{ optional($meet->to_date)->format('d.m.Y') }}</td>
                        <td class="text-end">
                            <a href="{{ route('meets.show', $meet) }}" class="btn btn-sm btn-primary">
                                Details
                            </a>
                            <a href="{{ route('meets.edit', $meet) }}" class="btn btn-sm btn-secondary">
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
