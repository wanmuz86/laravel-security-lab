<?php

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('unauthenticated API request is rejected', function () {
    $this->getJson('/api/secure-products')->assertUnauthorized();
});

test('read token can access read endpoint', function () {
    $token = $this->user->createToken('read-token', ['products:read'])->plainTextToken;

    $this->withToken($token)->getJson('/api/secure-products')->assertOk();
});

test('read token cannot modify products', function () {
    $token = $this->user->createToken('read-token', ['products:read'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/secure-products', ['name' => 'Unauthorized Product'])
        ->assertForbidden();
});

test('management token can access write endpoint', function () {
    $token = $this->user->createToken('manage-token', ['products:read', 'products:write'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/secure-products', ['name' => 'Authorized Product'])
        ->assertCreated();
});

test('revoked token cannot access API', function () {
    $token = $this->user->createToken('temporary-token', ['products:read']);
    $plainTextToken = $token->plainTextToken;
    $token->accessToken->delete();

    $this->withToken($plainTextToken)->getJson('/api/secure-products')->assertUnauthorized();
});

test('expired token cannot access API', function () {
    $token = $this->user->createToken('expired-token', ['products:read'], now()->subMinute());

    $this->withToken($token->plainTextToken)->getJson('/api/secure-products')->assertUnauthorized();
});

// ---- Token management endpoints (session-authenticated) ----------------------------------------

test('token endpoint issues restricted, expiring tokens', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/settings/api-tokens', ['name' => 'ci', 'access_level' => 'read'])
        ->assertCreated()
        ->assertJsonPath('abilities', ['products:read']);

    expect($response->json('token'))->toBeString();

    $stored = $this->user->tokens()->firstOrFail();
    expect($stored->abilities)->toBe(['products:read'])
        ->and($stored->expires_at)->not->toBeNull()
        ->and($stored->expires_at->isFuture())->toBeTrue();
});

test('token endpoint refuses unrestricted access levels', function () {
    $this->actingAs($this->user)
        ->postJson('/settings/api-tokens', ['name' => 'god', 'access_level' => '*'])
        ->assertUnprocessable();

    expect($this->user->tokens()->count())->toBe(0);
});

test('users can list and revoke only their own tokens', function () {
    $other = User::factory()->create();
    $otherToken = $other->createToken('other', ['products:read'])->accessToken;
    $mine = $this->user->createToken('mine', ['products:read'])->accessToken;

    $this->actingAs($this->user)
        ->getJson('/settings/api-tokens')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->user)
        ->deleteJson('/settings/api-tokens/'.$otherToken->id)
        ->assertNotFound();
    expect($other->tokens()->count())->toBe(1);

    $this->actingAs($this->user)->deleteJson('/settings/api-tokens/'.$mine->id)->assertNoContent();
    expect($this->user->tokens()->count())->toBe(0);
});

test('revoking through the endpoint blocks the bearer token', function () {
    $created = $this->user->createToken('temp', ['products:read']);

    $this->actingAs($this->user)
        ->deleteJson('/settings/api-tokens/'.$created->accessToken->id)
        ->assertNoContent();

    $this->withToken($created->plainTextToken)->getJson('/api/secure-products')->assertUnauthorized();
});

// ---- SEC-14: abilities are not ownership ------------------------------------------------------

test('a valid token cannot read another tenant project', function () {
    $orgB = Organization::factory()->create();
    $betaProject = Project::factory()->create(['organization_id' => $orgB->id, 'name' => 'Beta Secret']);
    $ownProject = Project::factory()->create(['organization_id' => $this->user->organization_id]);

    $token = $this->user->createToken('read', ['products:read'])->plainTextToken;

    $this->withToken($token)->getJson('/api/projects/'.$ownProject->id)->assertOk();

    $this->withToken($token)->getJson('/api/projects/'.$betaProject->id)
        ->assertNotFound()
        ->assertDontSee('Beta Secret');
});
