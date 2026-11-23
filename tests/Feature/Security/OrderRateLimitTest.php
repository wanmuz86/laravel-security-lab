<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function rateLimitOrder(User $user, string $reference = 'ORD-RATE-001'): Order
{
    return Order::forceCreate([
        'user_id' => $user->id,
        'reference' => $reference,
        'description' => 'Rate limit test',
        'total_cents' => 10000,
        'status' => 'draft',
    ]);
}

test('order update endpoint enforces rate limit', function () {
    $user = User::factory()->create();
    $order = rateLimitOrder($user);

    Sanctum::actingAs($user);

    for ($i = 0; $i < 20; $i++) {
        $this->patchJson("/api/orders/{$order->id}", [
            'description' => "Update {$i}",
        ])->assertOk();
    }

    $this->patchJson("/api/orders/{$order->id}", [
        'description' => 'Excessive request',
    ])->assertStatus(429);
});

test('the 429 response carries the rate limit headers', function () {
    $user = User::factory()->create();
    $order = rateLimitOrder($user);
    Sanctum::actingAs($user);

    for ($i = 0; $i < 20; $i++) {
        $this->patchJson("/api/orders/{$order->id}", ['description' => "Update {$i}"])->assertOk();
    }

    $response = $this->patchJson("/api/orders/{$order->id}", ['description' => 'Excessive'])->assertStatus(429);

    expect($response->headers->get('X-RateLimit-Limit'))->toBe('20')
        ->and($response->headers->get('X-RateLimit-Remaining'))->toBe('0')
        ->and($response->headers->get('Retry-After'))->not->toBeNull();
});

test('the process endpoint is limited to five requests per minute', function () {
    $user = User::factory()->create();
    $order = rateLimitOrder($user);
    Sanctum::actingAs($user);
    $url = "/api/orders/{$order->id}/process";

    $this->postJson($url, ['operation_key' => (string) Str::uuid()])->assertOk();

    for ($i = 0; $i < 4; $i++) {
        $this->postJson($url, ['operation_key' => (string) Str::uuid()])->assertUnprocessable();
    }

    $this->postJson($url, ['operation_key' => (string) Str::uuid()])->assertStatus(429);
});

test('read endpoints allow 60 requests per minute', function () {
    $user = User::factory()->create();
    $order = rateLimitOrder($user);
    Sanctum::actingAs($user);

    for ($i = 0; $i < 60; $i++) {
        $this->getJson("/api/orders/{$order->id}")->assertOk();
    }

    $this->getJson("/api/orders/{$order->id}")->assertStatus(429);
});

test('rate limits are tracked per user', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $aliceOrder = rateLimitOrder($alice, 'ORD-RATE-A');
    $bobOrder = rateLimitOrder($bob, 'ORD-RATE-B');

    Sanctum::actingAs($alice);
    for ($i = 0; $i < 20; $i++) {
        $this->patchJson("/api/orders/{$aliceOrder->id}", ['description' => "A{$i}"])->assertOk();
    }
    $this->patchJson("/api/orders/{$aliceOrder->id}", ['description' => 'A-over'])->assertStatus(429);

    Sanctum::actingAs($bob);
    $this->patchJson("/api/orders/{$bobOrder->id}", ['description' => 'B1'])->assertOk();
});
