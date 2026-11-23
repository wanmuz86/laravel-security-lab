@extends('layouts.app')

@section('content')
<div class="card">
    <div class="row-between">
        <h2>Projects</h2>
        <a class="btn" href="{{ route('projects.create') }}">New project</a>
    </div>
    <form method="GET" action="{{ route('projects.search') }}" class="row">
        <input type="text" name="q" placeholder="Search projects by name">
        <button class="primary" type="submit">Search</button>
    </form>
    <table>
        <thead><tr><th>ID</th><th>Name</th><th>Description</th></tr></thead>
        <tbody>
        @forelse($projects as $project)
            <tr>
                <td>{{ $project->id }}</td>
                <td><a href="{{ route('projects.show', $project->id) }}">{{ $project->name }}</a></td>
                <td>{{ $project->description }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="muted">No projects yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
