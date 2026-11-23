<?php

use Illuminate\Support\Facades\Cache;

const WEBHOOK_BODY = '{"type":"order.created","data":{"order_id":101}}';

beforeEach(function () {
    config()->set('integrations.webhook.secret', str_repeat('a', 64));
    config()->set('integrations.webhook.tolerance_seconds', 300);
    Cache::flush();
});

/** Build the server variables for a signed webhook request (the signature covers timestamp.eventId.rawBody). */
function webhookServer(string $body, ?string $eventId, int $timestamp, ?string $secret = null, ?string $signedBody = null): array
{
    $secret ??= config('integrations.webhook.secret');
    $signature = hash_hmac('sha256', $timestamp.'.'.$eventId.'.'.($signedBody ?? $body), $secret);

    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
    ];

    if ($eventId !== null) {
        $server['HTTP_X_WEBHOOK_ID'] = $eventId;
    }

    return $server;
}

function sendWebhook($test, array $server, string $body = WEBHOOK_BODY)
{
    return $test->call('POST', '/api/webhooks/training', [], [], [], $server, $body);
}

// ---- Lab tests ---------------------------------------------------------------------------------

test('valid webhook is accepted', function () {
    sendWebhook($this, webhookServer(WEBHOOK_BODY, 'evt_valid_001', time()))
        ->assertOk()
        ->assertExactJson(['received' => true, 'event_id' => 'evt_valid_001', 'type' => 'order.created']);
});

test('missing signature is rejected', function () {
    $this->postJson('/api/webhooks/training', [
        'type' => 'order.created',
        'data' => ['order_id' => 101],
    ])->assertUnauthorized();
});

test('invalid signature is rejected', function () {
    sendWebhook($this, webhookServer(WEBHOOK_BODY, 'evt_invalid_001', time(), str_repeat('b', 64)))->assertUnauthorized();
});

test('expired timestamp is rejected', function () {
    sendWebhook($this, webhookServer(WEBHOOK_BODY, 'evt_expired_001', time() - 600))->assertUnauthorized();
});

test('replayed webhook is rejected', function () {
    $server = webhookServer(WEBHOOK_BODY, 'evt_replay_001', time());

    sendWebhook($this, $server)->assertOk();
    sendWebhook($this, $server)->assertStatus(409)->assertExactJson(['message' => 'Duplicate webhook event.']);
});

// ---- Extra coverage ------------------------------------------------------------------------------

test('a body modified after signing is rejected', function () {
    $tampered = '{"type":"order.created","data":{"order_id":999}}';

    sendWebhook($this, webhookServer($tampered, 'evt_tamper_001', time(), null, WEBHOOK_BODY), $tampered)->assertUnauthorized();
});

test('a timestamp too far in the future is rejected', function () {
    sendWebhook($this, webhookServer(WEBHOOK_BODY, 'evt_future_001', time() + 600))->assertUnauthorized();
});

test('a timestamp just inside the tolerance is accepted', function () {
    sendWebhook($this, webhookServer(WEBHOOK_BODY, 'evt_edge_001', time() - 290))->assertOk();
});

test('a missing event id is rejected', function () {
    sendWebhook($this, webhookServer(WEBHOOK_BODY, null, time()))->assertUnauthorized();
});

test('a signature made for a different event id is rejected', function () {
    $server = webhookServer(WEBHOOK_BODY, 'evt_original_001', time());
    $server['HTTP_X_WEBHOOK_ID'] = 'evt_swapped_001';

    sendWebhook($this, $server)->assertUnauthorized();
});

test('malformed signature headers are rejected', function () {
    $server = webhookServer(WEBHOOK_BODY, 'evt_format_001', time());

    $server['HTTP_X_WEBHOOK_SIGNATURE'] = strtoupper($server['HTTP_X_WEBHOOK_SIGNATURE']);
    sendWebhook($this, $server)->assertUnauthorized();

    $server['HTTP_X_WEBHOOK_SIGNATURE'] = 'not-a-signature';
    sendWebhook($this, $server)->assertUnauthorized();
});

test('an invalid event payload is rejected with 422', function () {
    $body = '{"type":"order.deleted","data":{"order_id":101}}';

    sendWebhook($this, webhookServer($body, 'evt_payload_001', time()), $body)->assertStatus(422);
});

test('a new event id is accepted after an earlier event', function () {
    sendWebhook($this, webhookServer(WEBHOOK_BODY, 'evt_seq_001', time()))->assertOk();
    sendWebhook($this, webhookServer(WEBHOOK_BODY, 'evt_seq_002', time()))->assertOk();
});

test('a rejected request does not consume the event id', function () {
    sendWebhook($this, webhookServer(WEBHOOK_BODY, 'evt_burn_001', time(), str_repeat('b', 64)))->assertUnauthorized();
    sendWebhook($this, webhookServer(WEBHOOK_BODY, 'evt_burn_001', time()))->assertOk();
});

test('verification is unavailable when no secret is configured', function () {
    config()->set('integrations.webhook.secret', null);

    sendWebhook($this, webhookServer(WEBHOOK_BODY, 'evt_nosecret_001', time(), str_repeat('a', 64)))->assertStatus(503);
});

test('a too-short secret is treated as not configured', function () {
    config()->set('integrations.webhook.secret', 'short');

    sendWebhook($this, webhookServer(WEBHOOK_BODY, 'evt_shortsecret_001', time(), 'short'))->assertStatus(503);
});
