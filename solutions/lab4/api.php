<?php

use App\Http\Controllers\Api\ProjectApiController;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// JSON list of the signed-in user's organization projects (session-authenticated for the lab).
Route::middleware(['web', 'auth'])->get('/projects', function (Request $request) {
    return Project::where('organization_id', $request->user()->organization_id)
        ->get(['id', 'name', 'description']);
});

// Lab 4 Part E: token-authenticated routes with ability checks.
Route::middleware(['auth:sanctum', 'abilities:products:read'])->get('/secure-products', function () {
    return response()->json([
        'data' => [
            ['id' => 1, 'name' => 'Wireless Mouse'],
        ],
    ]);
});

Route::middleware(['auth:sanctum', 'abilities:products:write'])->post('/secure-products', function (Request $request) {
    return response()->json([
        'message' => 'Product write permission accepted.',
    ], 201);
});

// SEC-14: ability + tenant ownership (Policy).
Route::middleware(['auth:sanctum', 'abilities:products:read'])
    ->get('/projects/{id}', [ProjectApiController::class, 'show']);
