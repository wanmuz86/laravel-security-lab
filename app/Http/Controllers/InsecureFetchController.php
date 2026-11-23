<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Lab 6 Part A — INTENTIONALLY INSECURE, for inspection and the controlled local demo only.
 * Not routed by default. To demonstrate local SSRF (Step A4), add this to routes/web.php inside the
 * 'auth' group, run the demo, and then REMOVE the route again:
 *
 *   Route::get('/insecure-fetch', [\App\Http\Controllers\InsecureFetchController::class, 'fetch']);
 */
class InsecureFetchController extends Controller
{
    public function fetch(Request $request)
    {
        $url = $request->input('url');
        $response = Http::get($url);

        return response()->json([
            'status' => $response->status(),
            'content' => $response->body(),
        ]);
    }
}
