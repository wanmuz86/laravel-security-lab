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

class PasswordResetController extends Controller
{

    //  Called when use pressed the button
    public function requestReset(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'], // Validate directly
        ]);

        $email = Str::lower(trim($data['email']));    /// format the email

        // Send the password reset email
        // using password broker - use Illuminate\Support\Facades\Password;
        Password::broker()->sendResetLink([
            'email' => $email,
        ]);

        return response()->json([
            'message' => 'If the account exists, reset instructions will be sent.',
        ]);
    }

      //  Called when use pressed "passwrod reset button" after pressing the link in the email
    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'], # token
            'email' => ['required', 'email'], #email
            'password' => [ # new password is valid
                'required',
                'confirmed',
                PasswordRule::min(6) # follow this rules 
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        # Use Password Broker to reset user' password 
        $status = Password::broker()->reset( 
            $data,
            function ($user, $password) {
                DB::transaction(function () use (
                    $user,
                    $password
                ) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),    #once the password is reset, update the DB with new PW
                    ])->save();

                    // Revoke existing API tokens.
                    $user->tokens()->delete();  # Delete API token

                    // Revoke database-backed web sessions.
                    DB::table('sessions')
                        ->where('user_id', $user->getAuthIdentifier())
                        ->delete();   # Delete sessions
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
