@extends('layouts.app')

@section('content')
<div class="card narrow">
    <h2>Forgot password</h2>
    <form method="POST" action="{{ url('/forgot-password') }}">
        @csrf
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus>
        <button class="primary" type="submit">Send reset link</button>
    </form>
    <p class="muted">Lab 4 Part D adds the POST handler (JSON response). Mail goes to storage/logs/laravel.log.</p>
    <p><a href="{{ route('login') }}">Back to sign in</a></p>
</div>
@endsection
