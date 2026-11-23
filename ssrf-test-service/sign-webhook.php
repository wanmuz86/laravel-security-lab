<?php
// Lab 6 Part D7: prints a correctly signed training webhook request.
//
// Run INSIDE the container (the host has no PHP):
//   docker compose exec app php ssrf-test-service/sign-webhook.php
//   docker compose exec app php ssrf-test-service/sign-webhook.php evt_training_002
//   docker compose exec app php ssrf-test-service/sign-webhook.php evt_old_001 -600   # timestamp 10 min in the past
//
// The secret is read from WEBHOOK_SHARED_SECRET (environment) or, failing that, from the container's .env.

$secret = getenv('WEBHOOK_SHARED_SECRET') ?: null;

if ($secret === null && is_readable(__DIR__.'/../.env')) {
    foreach (file(__DIR__.'/../.env', FILE_IGNORE_NEW_LINES) as $line) {
        if (str_starts_with($line, 'WEBHOOK_SHARED_SECRET=')) {
            $secret = trim(substr($line, strlen('WEBHOOK_SHARED_SECRET=')), " \t\"'");
        }
    }
}

if (! is_string($secret) || strlen($secret) < 32) {
    exit("Configure WEBHOOK_SHARED_SECRET first (docker/env.lab, then docker compose restart app).\n");
}

$eventId = $argv[1] ?? 'evt_training_001';
$offset = (int) ($argv[2] ?? 0);
$timestamp = (string) (time() + $offset);
$body = json_encode([
    'type' => 'order.created',
    'data' => [
        'order_id' => 101,
    ],
], JSON_THROW_ON_ERROR);

$message = $timestamp.'.'.$eventId.'.'.$body;
$signature = hash_hmac('sha256', $message, $secret);

echo "Timestamp: {$timestamp}\n";
echo "Event ID: {$eventId}\n";
echo "Signature: {$signature}\n";
echo "Body: {$body}\n\n";
echo "curl -i -X POST http://localhost:8000/api/webhooks/training \\\n";
echo "  -H 'Content-Type: application/json' -H 'Accept: application/json' \\\n";
echo "  -H 'X-Webhook-Id: {$eventId}' -H 'X-Webhook-Timestamp: {$timestamp}' \\\n";
echo "  -H 'X-Webhook-Signature: {$signature}' \\\n";
echo "  --data '{$body}'\n";
