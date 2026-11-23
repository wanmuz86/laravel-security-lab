<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('integrations.webhook.secret');
        $timestamp = $request->header('X-Webhook-Timestamp');
        $eventId = $request->header('X-Webhook-Id');
        $signature = $request->header('X-Webhook-Signature');

        if (! is_string($secret) || strlen($secret) < 32) {
            abort(503, 'Webhook verification unavailable.');
        }

        if (
            ! is_string($timestamp) ||
            ! preg_match('/^[0-9]{10,12}$/', $timestamp) ||
            ! is_string($eventId) ||
            ! preg_match('/^[A-Za-z0-9_-]{1,100}$/', $eventId) ||
            ! is_string($signature) ||
            ! preg_match('/^[a-f0-9]{64}$/', $signature)
        ) {
            return response()->json([
                'message' => 'Invalid webhook authentication.',
            ], 401);
        }

        $tolerance = (int) config('integrations.webhook.tolerance_seconds', 300);

        if (abs(time() - (int) $timestamp) > $tolerance) {
            return response()->json([
                'message' => 'Webhook timestamp is invalid.',
            ], 401);
        }

        $payload = $request->getContent();
        $signedMessage = $timestamp.'.'.$eventId.'.'.$payload;
        $expected = hash_hmac('sha256', $signedMessage, $secret);

        if (! hash_equals($expected, $signature)) {
            return response()->json([
                'message' => 'Invalid webhook authentication.',
            ], 401);
        }

        $request->attributes->set('verified_webhook_id', $eventId);

        return $next($request);
    }
}
