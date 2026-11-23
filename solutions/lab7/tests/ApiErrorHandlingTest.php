<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Psr\Log\LoggerInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();
    $this->bobOrder = Order::forceCreate([
        'user_id' => $this->bob->id, 'reference' => 'ORD-BOB-001', 'description' => 'Bob order', 'total_cents' => 100, 'status' => 'draft',
    ]);
});

test('API errors are JSON with the expected status codes', function () {
    $this->getJson("/api/orders/{$this->bobOrder->id}")->assertUnauthorized()->assertJsonStructure(['message']);

    Sanctum::actingAs($this->alice);

    $this->getJson("/api/orders/{$this->bobOrder->id}")->assertForbidden()->assertJsonStructure(['message']);
    $this->getJson('/api/orders/999999')->assertNotFound()->assertJsonStructure(['message']);
    $this->patchJson('/api/orders/'.Order::forceCreate([
        'user_id' => $this->alice->id, 'reference' => 'ORD-A', 'description' => 'A', 'total_cents' => 1, 'status' => 'draft',
    ])->id, ['status' => 'processed'])->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
});

test('error responses do not leak internal details', function () {
    Sanctum::actingAs($this->alice);

    $bodies = [
        $this->getJson('/api/orders/999999')->getContent(),
        $this->getJson("/api/orders/{$this->bobOrder->id}")->getContent(),
        $this->getJson('/api/does-not-exist')->getContent(),
    ];

    foreach ($bodies as $body) {
        expect($body)->not->toContain('App\\Models')
            ->not->toContain('Illuminate')
            ->not->toContain('vendor/')
            ->not->toContain('"trace"')
            ->not->toContain('"file"')
            ->not->toContain('select ');
    }
});

test('an unexpected exception returns a generic 500', function () {
    config(['app.debug' => false]);
    Route::get('/api/_test/boom', fn () => throw new RuntimeException('SECRET-DB-PASSWORD leaked from /var/www/html'));

    $response = $this->getJson('/api/_test/boom');

    $response->assertStatus(500)->assertExactJson(['message' => 'Server Error']);
    expect($response->getContent())->not->toContain('SECRET-DB-PASSWORD')->not->toContain('/var/www');
});

test('authorization denials are written to the security log without sensitive data', function () {
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')->once()->with(
        'security_event',
        Mockery::on(function (array $context) {
            return $context['event'] === 'authorization_denied'
                && $context['user_id'] === $this->alice->id
                && isset($context['ip'], $context['timestamp'])
                && ! array_key_exists('authorization', $context)
                && ! array_key_exists('token', $context)
                && ! array_key_exists('password', $context);
        })
    );
    Log::partialMock()->shouldReceive('channel')->with('stack')->andReturn($logger);

    Sanctum::actingAs($this->alice);

    $this->withHeaders(['Authorization' => 'Bearer should-never-be-logged'])
        ->getJson("/api/orders/{$this->bobOrder->id}")
        ->assertForbidden();
});
