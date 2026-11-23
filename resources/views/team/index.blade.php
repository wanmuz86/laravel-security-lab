@extends('layouts.app')

@section('content')
<div class="card">
    <h2>Team members</h2>
    <table>
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th></th></tr></thead>
        <tbody>
        @foreach($members as $member)
            <tr>
                <td>{{ $member->id }}</td>
                <td>{{ $member->name }}</td>
                <td>{{ $member->email }}</td>
                <td>{{ $member->role }}</td>
                <td>
                    <form method="POST" action="{{ route('team.role', $member->id) }}" class="row">
                        @csrf
                        @method('PATCH')
                        <select name="role">
                            <option value="member" @selected($member->role === 'member')>member</option>
                            <option value="admin" @selected($member->role === 'admin')>admin</option>
                        </select>
                        <button class="primary" type="submit">Update</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
