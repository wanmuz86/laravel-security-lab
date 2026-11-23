<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/** LAB 4 REFERENCE SOLUTION: generic responses, single-use expiring tokens, session/token revocation. */
class PasswordResetController extends Controller
{
    public function requestReset(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = Str::lower(trim($data['email']));

        Password::broker()->sendResetLink([
            'email' => $email,
        ]);

        return response()->json([
            'message' => 'If the account exists, reset instructions will be sent.',
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $status = Password::broker()->reset(
            $data,
            function ($user, $password) {
                DB::transaction(function () use ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();

                    // Revoke existing API tokens.
                    $user->tokens()->delete();

                    // Revoke database-backed web sessions.
                    DB::table('sessions')
                        ->where('user_id', $user->getAuthIdentifier())
                        ->delete();
                });
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Unable to reset password.',
            ], 422);
        }

        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }
}
