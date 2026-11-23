<?php

use App\Http\Controllers\Api\ProjectApiController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderProcessingController;
use App\Http\Controllers\WebhookController;
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

// Lab 6 Parts D/E: HMAC-verified webhook with replay protection (no session or CSRF; the signature is the credential).
Route::post('/webhooks/training', [
    WebhookController::class,
    'receive',
])->middleware([
    'verified.webhook',
    'webhook.replay',
]);

// Lab 7: secured order API (the intentionally vulnerable /api/lab-vulnerable/* routes are removed).
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/orders', [OrderController::class, 'index'])
        ->middleware('throttle:orders-read');

    Route::get('/orders/{order}', [OrderController::class, 'show'])
        ->middleware('throttle:orders-read');

    Route::patch('/orders/{order}', [OrderController::class, 'update'])
        ->middleware('throttle:orders-update');

    Route::post('/orders/{order}/process', [OrderProcessingController::class, 'store'])
        ->middleware('throttle:orders-process');
});
