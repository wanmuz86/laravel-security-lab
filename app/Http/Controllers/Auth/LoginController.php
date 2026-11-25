<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
public function store(LoginRequest $request)
{
$email = Str::lower(
trim($request->input('email'))
);
    // Save the IP + email information in RateLimiter 
    // To detect if the user is trying to attempt to attack
    // (hash with sha256)
    $key = 'login:' . hash(
        'sha256',
        $email . '|' . $request->ip()
    );

    // If RateLimiter detect that user has try more than 5 times,
    // Return 429 -> Too many atemppts, retry_after xx seconds
    if (RateLimiter::tooManyAttempts($key, 5)) {
        return response()->json([
            'message' => 'Too many login attempts.',
            'retry_after' => RateLimiter::availableIn($key),
        ], 429);
    }

    $credentials = [
        'email' => $email,
        'password' => $request->input('password'),
    ];

    // Attempt to login
    if (! Auth::attempt($credentials)) {
        RateLimiter::hit($key, 60); // If fail, record as fail and increase the counter

        throw ValidationException::withMessages([
            'email' => 'The provided credentials are incorrect.',
        ]);
    }

    RateLimiter::clear($key);
    $request->session()->regenerate();

    return redirect()->intended('/dashboard');
}

public function destroy(Request $request)
{
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
}

}
