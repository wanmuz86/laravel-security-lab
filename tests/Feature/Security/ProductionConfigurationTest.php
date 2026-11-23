<?php

use Dotenv\Dotenv;

// Lab 9 Part F3: required production hardening settings, checked from a non-secret fixture and the live app.

function productionEnv(): array
{
    return Dotenv::parse(file_get_contents(base_path('tests/Fixtures/production.env.example')));
}

test('production deployment fixture is hardened', function () {
    $env = productionEnv();

    expect($env['APP_ENV'])->toBe('production')
        ->and($env['APP_DEBUG'])->toBe('false')
        ->and($env['APP_URL'])->toStartWith('https://')
        ->and($env['SESSION_SECURE_COOKIE'])->toBe('true')
        ->and($env['SESSION_HTTP_ONLY'])->toBe('true')
        ->and($env['SESSION_SAME_SITE'])->toBeIn(['lax', 'strict']);
});

test('no application key or credential is committed in the production fixture', function () {
    $env = productionEnv();

    expect($env['APP_KEY'])->toBeEmpty();

    foreach ($env as $name => $value) {
        if (preg_match('/(PASSWORD|SECRET|TOKEN|API_KEY)/', $name)) {
            expect($value)->toBeEmpty("{$name} must not hold a committed value");
        }
    }
});

test('session cookies are http-only and same-site restricted by default', function () {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.same_site'))->toBeIn(['lax', 'strict']);
});

test('web responses carry the required security headers', function () {
    $response = $this->get('/login');

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'");
});
