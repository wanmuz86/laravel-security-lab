@extends('layouts.app')

@section('content')
<div class="card narrow">
    <h2>Sign in</h2>
    <form method="POST" action="{{ url('/login') }}">
        @csrf
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus>
        <label>Password</label>
        <input type="password" name="password" required>
        <button class="primary" type="submit">Log in</button>
    </form>
    <p><a href="{{ route('password.request') }}">Forgot your password?</a></p>
    <p class="muted">Training lab. Use the seeded test accounts from the lab README.</p>
</div>
@endsection
