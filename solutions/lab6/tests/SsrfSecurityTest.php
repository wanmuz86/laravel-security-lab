<?php

use App\Models\User;
use App\Services\SafeUrlValidator;
use App\Services\SecureHttpFetcher;

beforeEach(function () {
    config()->set('integrations.fetcher.allowed_hosts', ['approved.example.com']);
    $this->user = User::factory()->create();
});

test('guests cannot use the fetcher', function () {
    $this->postJson('/integrations/fetch', ['url' => 'https://approved.example.com'])->assertUnauthorized();
});

test('a missing url fails validation', function () {
    $this->actingAs($this->user)->postJson('/integrations/fetch', [])->assertStatus(422)->assertJsonValidationErrors('url');
});

test('internal and unsafe destinations return a generic 422', function (string $url) {
    $this->actingAs($this->user)
        ->postJson('/integrations/fetch', ['url' => $url])
        ->assertStatus(422)
        ->assertExactJson(['message' => 'Destination is not permitted.']);
})->with([
    'loopback over http' => 'http://127.0.0.1:9001/internal.php',
    'localhost over http' => 'http://localhost:9001',
    'ip literal over https' => 'https://127.0.0.1',
    'private ip, not allowlisted' => 'https://192.168.1.10',
    'file scheme' => 'file:///etc/hosts',
    'userinfo trick' => 'https://approved.example.com@localhost/',
    'nonstandard port' => 'https://approved.example.com:8443/',
    'unapproved host' => 'https://unapproved.example.org/',
]);

test('an allowlisted host that resolves to a private address is rejected before any connection', function () {
    $this->app->instance(
        SafeUrlValidator::class,
        new SafeUrlValidator(fn () => [['type' => 'A', 'ip' => '10.0.0.5']])
    );

    $this->actingAs($this->user)
        ->postJson('/integrations/fetch', ['url' => 'https://approved.example.com/'])
        ->assertStatus(422)
        ->assertExactJson(['message' => 'Destination is not permitted.']);
});

test('upstream failures return a generic 502 without internal details', function () {
    $this->mock(SecureHttpFetcher::class, function ($mock) {
        $mock->shouldReceive('fetch')->once()->andThrow(new RuntimeException('connect to 10.0.0.5:443 refused'));
    });

    $response = $this->actingAs($this->user)->postJson('/integrations/fetch', ['url' => 'https://approved.example.com/']);

    $response->assertStatus(502)->assertExactJson(['message' => 'Unable to fetch the resource.']);
    expect($response->getContent())->not->toContain('10.0.0.5');
});

test('a successful fetch returns the status and content', function () {
    $this->mock(SecureHttpFetcher::class, function ($mock) {
        $mock->shouldReceive('fetch')->once()->with('https://approved.example.com/')->andReturn(['status' => 200, 'body' => 'ok']);
    });

    $this->actingAs($this->user)
        ->postJson('/integrations/fetch', ['url' => 'https://approved.example.com/'])
        ->assertOk()
        ->assertExactJson(['status' => 200, 'content' => 'ok']);
});
