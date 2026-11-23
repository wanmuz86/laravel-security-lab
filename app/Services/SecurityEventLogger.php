<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SecurityEventLogger
{
    /**
     * Record a security event. Callers must pass only safe context (ids, route names, IP addresses):
     * never credentials, bearer tokens, cookies, card data or raw request bodies.
     */
    public function record(string $event, array $context = []): void
    {
        Log::channel('stack')->warning(
            'security_event',
            array_merge([
                'event' => $event,
                'timestamp' => now()->toISOString(),
            ], $context)
        );
    }
}
