<?php

function cspNonce(string $policy): string
{
    preg_match("/'nonce-([^']+)'/", $policy, $matches);

    return $matches[1] ?? '';
}

test('security headers are present', function () {
    $response = $this->get('/security-demo');
    $response->assertOk();

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    expect($response->headers->get('Content-Security-Policy'))->toContain("object-src 'none'");
});

test('CSP contains a nonce', function () {
    $policy = $this->get('/security-demo')->headers->get('Content-Security-Policy');

    expect($policy)->toMatch("/'nonce-[A-Za-z0-9+\/=+]+'/");
});

test('CSP does not permit unsafe inline scripts', function () {
    $policy = $this->get('/security-demo')->headers->get('Content-Security-Policy');

    expect($policy)
        ->not->toContain("'unsafe-inline'")
        ->not->toContain("'unsafe-eval'");
});

test('CSP blocks framing and restricts forms and base uri', function () {
    $policy = $this->get('/security-demo')->headers->get('Content-Security-Policy');

    expect($policy)->toContain("frame-ancestors 'none'")
        ->toContain("form-action 'self'")
        ->toContain("base-uri 'self'");
});

test('the nonce is different for every response', function () {
    $first = cspNonce($this->get('/security-demo')->headers->get('Content-Security-Policy'));
    $second = cspNonce($this->get('/security-demo')->headers->get('Content-Security-Policy'));

    expect($first)->not->toBe('')->and($second)->not->toBe('')->and($first)->not->toBe($second);
});

test('only the trusted script carries the nonce from the header', function () {
    $response = $this->get('/security-demo');
    $nonce = cspNonce($response->headers->get('Content-Security-Policy'));

    $response->assertSee('<script nonce="'.$nonce.'">', false);

    // exactly one script has a nonce attribute; the unauthorized inline script has none
    expect(substr_count($response->getContent(), 'nonce='))->toBe(1)
        ->and(substr_count($response->getContent(), '<script>'))->toBe(1);
});

test('application pages carry the policy and use no inline styles or scripts', function () {
    $response = $this->get('/login');
    $response->assertOk()->assertHeader('Content-Security-Policy');

    $html = $response->getContent();

    expect($html)->not->toContain('<style')
        ->not->toContain('style="')
        ->not->toContain('onsubmit=')
        ->not->toContain('onclick=');
});

test('HSTS is only sent over https', function () {
    $this->get('/security-demo')->assertHeaderMissing('Strict-Transport-Security');

    $this->get('https://localhost/security-demo')->assertHeader('Strict-Transport-Security');
});
