<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();

    $this->aliceOrder = Order::forceCreate([
        'user_id' => $this->alice->id,
        'reference' => 'ORD-ALICE-001',
        'description' => 'Alice training order',
        'total_cents' => 15000,
        'status' => 'draft',
        'payment_reference' => 'PRIVATE-PAY-001',
        'internal_notes' => 'Confidential notes',
    ]);

    $this->bobOrder = Order::forceCreate([
        'user_id' => $this->bob->id,
        'reference' => 'ORD-BOB-001',
        'description' => 'Bob training order',
        'total_cents' => 25000,
        'status' => 'draft',
    ]);
});

// ---- Lab tests --------------------------------------------------------------------------------

test('unauthenticated request is rejected', function () {
    $this->getJson("/api/orders/{$this->aliceOrder->id}")
        ->assertUnauthorized();
});

test('customer can view own order', function () {
    Sanctum::actingAs($this->alice);

    $this->getJson("/api/orders/{$this->aliceOrder->id}")
        ->assertOk();
});

test('customer cannot view another customer order', function () {
    Sanctum::actingAs($this->alice);

    $this->getJson("/api/orders/{$this->bobOrder->id}")
        ->assertForbidden();
});

test('customer cannot update another customer order', function () {
    Sanctum::actingAs($this->alice);

    $this->patchJson("/api/orders/{$this->bobOrder->id}", [
        'description' => 'Unauthorized update',
    ])->assertForbidden();

    expect($this->bobOrder->fresh()->description)->toBe('Bob training order');
});

test('API response excludes sensitive fields', function () {
    Sanctum::actingAs($this->alice);

    $this->getJson("/api/orders/{$this->aliceOrder->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.internal_notes')
        ->assertJsonMissingPath('data.payment_reference')
        ->assertJsonMissingPath('data.user_id');
});

test('mass assignment attempt is rejected', function () {
    Sanctum::actingAs($this->alice);

    $this->patchJson("/api/orders/{$this->aliceOrder->id}", [
        'description' => 'Changed',
        'total_cents' => 1,
        'status' => 'processed',
    ])->assertUnprocessable();

    $this->aliceOrder->refresh();
    expect($this->aliceOrder->total_cents)->toBe(15000);
    expect($this->aliceOrder->status)->toBe('draft');
});

test('allowed description update succeeds', function () {
    Sanctum::actingAs($this->alice);

    $this->patchJson("/api/orders/{$this->aliceOrder->id}", [
        'description' => 'Updated description',
    ])->assertOk();

    expect($this->aliceOrder->fresh()->description)->toBe('Updated description');
});

test('order cannot be processed twice', function () {
    Sanctum::actingAs($this->alice);
    $url = "/api/orders/{$this->aliceOrder->id}/process";

    $this->postJson($url, [
        'operation_key' => (string) Str::uuid(),
    ])->assertOk();

    $this->postJson($url, [
        'operation_key' => (string) Str::uuid(),
    ])->assertUnprocessable();

    $this->assertDatabaseCount('order_processings', 1);
    expect($this->aliceOrder->fresh()->status)->toBe('processed');
});

test('customer cannot process another customer order', function () {
    Sanctum::actingAs($this->alice);

    $this->postJson("/api/orders/{$this->bobOrder->id}/process", [
        'operation_key' => (string) Str::uuid(),
    ])->assertForbidden();
});

// ---- Extra coverage ---------------------------------------------------------------------------

test('the order list contains only the signed-in customers orders', function () {
    Order::forceCreate([
        'user_id' => $this->alice->id, 'reference' => 'ORD-ALICE-002', 'description' => 'Second', 'total_cents' => 100, 'status' => 'draft',
    ]);
    Sanctum::actingAs($this->alice);

    $response = $this->getJson('/api/orders')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and(collect($response->json('data'))->pluck('reference')->all())->not->toContain('ORD-BOB-001');
    $response->assertJsonMissingPath('data.0.internal_notes')->assertJsonMissingPath('data.0.user_id');
});

test('guests cannot list or process orders', function () {
    $this->getJson('/api/orders')->assertUnauthorized();
    $this->postJson("/api/orders/{$this->aliceOrder->id}/process", ['operation_key' => (string) Str::uuid()])->assertUnauthorized();
    $this->patchJson("/api/orders/{$this->aliceOrder->id}", ['description' => 'x'])->assertUnauthorized();
});

test('a missing order returns a generic 404', function () {
    Sanctum::actingAs($this->alice);

    $this->getJson('/api/orders/999999')->assertNotFound()->assertExactJson(['message' => 'Resource not found.']);
});

test('every server-controlled field is rejected individually and nothing changes', function (string $field, mixed $value) {
    Sanctum::actingAs($this->alice);
    $before = $this->aliceOrder->fresh()->getAttributes();

    $this->patchJson("/api/orders/{$this->aliceOrder->id}", [
        'description' => 'Harmless change',
        $field => $value,
    ])->assertUnprocessable()->assertJsonValidationErrors($field);

    expect($this->aliceOrder->fresh()->getAttributes())->toEqual($before);
})->with([
    'user_id' => ['user_id', 2],
    'total_cents' => ['total_cents', 1],
    'status' => ['status', 'processed'],
    'payment_reference' => ['payment_reference', 'PAY-HACKED'],
    'internal_notes' => ['internal_notes', 'Modified by customer'],
    'processed_at' => ['processed_at', '2026-01-01 00:00:00'],
]);

test('an invalid description is rejected', function () {
    Sanctum::actingAs($this->alice);

    $this->patchJson("/api/orders/{$this->aliceOrder->id}", ['description' => str_repeat('a', 501)])
        ->assertUnprocessable()->assertJsonValidationErrors('description');
});

test('a processed order can no longer be edited', function () {
    $this->aliceOrder->status = 'processed';
    $this->aliceOrder->save();
    Sanctum::actingAs($this->alice);

    $this->patchJson("/api/orders/{$this->aliceOrder->id}", ['description' => 'Too late'])->assertForbidden();
});

test('the model refuses mass assignment of server-controlled fields', function () {
    $order = new Order;
    $order->fill(['description' => 'ok', 'user_id' => 99, 'total_cents' => 1, 'status' => 'processed', 'internal_notes' => 'x']);

    expect($order->getAttributes())->toBe(['description' => 'ok']);
});

test('processing requires a uuid operation key', function (mixed $key) {
    Sanctum::actingAs($this->alice);

    $this->postJson("/api/orders/{$this->aliceOrder->id}/process", $key === null ? [] : ['operation_key' => $key])
        ->assertUnprocessable()->assertJsonValidationErrors('operation_key');

    expect($this->aliceOrder->fresh()->status)->toBe('draft');
})->with(['missing' => [null], 'not a uuid' => ['abc'], 'number' => [12345]]);

test('the processing response contains no sensitive fields', function () {
    Sanctum::actingAs($this->alice);

    $this->postJson("/api/orders/{$this->aliceOrder->id}/process", ['operation_key' => (string) Str::uuid()])
        ->assertOk()
        ->assertJsonPath('data.status', 'processed')
        ->assertJsonMissingPath('data.internal_notes')
        ->assertJsonMissingPath('data.payment_reference')
        ->assertJsonMissingPath('data.processed_at');

    expect($this->aliceOrder->fresh()->processed_at)->not->toBeNull();
});

test('an operation key cannot be reused for a different order', function () {
    $second = Order::forceCreate([
        'user_id' => $this->alice->id, 'reference' => 'ORD-ALICE-002', 'description' => 'Second', 'total_cents' => 100, 'status' => 'draft',
    ]);
    $key = (string) Str::uuid();
    Sanctum::actingAs($this->alice);

    $this->postJson("/api/orders/{$this->aliceOrder->id}/process", ['operation_key' => $key])->assertOk();
    $this->postJson("/api/orders/{$second->id}/process", ['operation_key' => $key])->assertUnprocessable();

    expect($second->fresh()->status)->toBe('draft');
    $this->assertDatabaseCount('order_processings', 1);
});

test('the intentionally vulnerable lab routes are gone', function () {
    Sanctum::actingAs($this->alice);

    $this->getJson("/api/lab-vulnerable/orders/{$this->bobOrder->id}")->assertNotFound();
    $this->patchJson("/api/lab-vulnerable/orders/{$this->bobOrder->id}", ['total_cents' => 1])->assertNotFound();
    $this->postJson("/api/lab-vulnerable/orders/{$this->bobOrder->id}/process")->assertNotFound();
});
