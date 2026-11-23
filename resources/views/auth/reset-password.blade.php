@extends('layouts.app')

@section('content')
<div class="card narrow">
    <h2>Reset password</h2>
    <form method="POST" action="{{ url('/reset-password') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email', $email) }}" required>
        <label>New password</label>
        <input type="password" name="password" required>
        <label>Confirm new password</label>
        <input type="password" name="password_confirmation" required>
        <button class="primary" type="submit">Reset password</button>
    </form>
    <p class="muted">Lab 4 Part D adds the POST handler (JSON response).</p>
</div>
@endsection
