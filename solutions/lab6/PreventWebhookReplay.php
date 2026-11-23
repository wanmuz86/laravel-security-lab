<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PreventWebhookReplay
{
    public function handle(Request $request, Closure $next): Response
    {
        $eventId = $request->attributes->get('verified_webhook_id');

        if (! is_string($eventId)) {
            return response()->json([
                'message' => 'Webhook not verified.',
            ], 401);
        }

        $key = 'webhook:replay:'.hash('sha256', $eventId);
        $reserved = Cache::add($key, true, now()->addMinutes(10));

        if (! $reserved) {
            return response()->json([
                'message' => 'Duplicate webhook event.',
            ], 409);
        }

        return $next($request);
    }
}
