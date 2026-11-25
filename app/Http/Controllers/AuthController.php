<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * LAB 4 (intentionally insecure starting point). Inspect, then harden:
 *  - no session ID regeneration after a successful login (session fixation)
 *  - logout does not invalidate the session or rotate the CSRF token
 *  - no rate limiting on repeated failed logins
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /// Session needs to be regenarate
        // Call 
        //  $request->session()->regenerate()
        // $request->session()->invalidate()

        if (! Auth::attempt($credentials)) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
        }


        // $request->session()->invalidate();
        $request->session()->regenerate(); // this replace the session 

        return redirect()->intended(route('projects.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        /// Session needs to be regenarate
        // Call 
         $request->session()->regenerateToken(); // This replace csrf token
        $request->session()->invalidate();


        return redirect()->route('login');
    }
}
