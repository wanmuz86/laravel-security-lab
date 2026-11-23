<?php

namespace App\Http\Controllers;

/** LAB 4 REFERENCE SOLUTION: login/logout handling moved to Auth\LoginController. */
class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }
}
