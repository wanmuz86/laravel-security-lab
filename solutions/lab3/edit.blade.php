@extends('layouts.app')

@section('content')
<div class="card">
    <h2>Edit project #{{ $project->id }}</h2>
    <form method="POST" action="{{ route('projects.update', $project->id) }}">
        @csrf
        @method('PUT')
        <label>Name</label>
        <input type="text" name="name" value="{{ old('name', $project->name) }}" required>
        <label>Description</label>
        <textarea name="description" rows="4">{{ old('description', $project->description) }}</textarea>
        <button class="primary" type="submit">Save</button>
    </form>
</div>
@endsection
