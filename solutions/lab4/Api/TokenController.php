<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** LAB 4 REFERENCE SOLUTION: users manage only their own Sanctum tokens; abilities are restricted. */
class TokenController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'access_level' => ['required', Rule::in(['read', 'manage'])],
        ]);

        $abilities = match ($data['access_level']) {
            'read' => ['products:read'],
            'manage' => ['products:read', 'products:write'],
        };

        $token = $request->user()->createToken(
            $data['name'],
            $abilities,
            now()->addDays(7)
        );

        return response()->json([
            'message' => 'Token created successfully.',
            'token' => $token->plainTextToken,
            'abilities' => $abilities,
        ], 201);
    }

    public function index(Request $request)
    {
        return response()->json([
            'data' => $request->user()
                ->tokens()
                ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at']),
        ]);
    }

    public function destroy(Request $request, int $tokenId)
    {
        $deleted = $request->user()->tokens()->whereKey($tokenId)->delete();

        if ($deleted === 0) {
            return response()->json(['message' => 'Token not found.'], 404);
        }

        return response()->noContent();
    }

    public function revokeAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->noContent();
    }
}
