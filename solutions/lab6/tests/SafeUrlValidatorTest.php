<?php

use App\Services\SafeUrlValidator;

/** A resolver that returns the given addresses, so tests never touch real DNS. */
function fixedDns(array $ips): Closure
{
    return fn (string $host) => array_map(
        fn (string $ip) => str_contains($ip, ':') ? ['type' => 'AAAA', 'ipv6' => $ip] : ['type' => 'A', 'ip' => $ip],
        $ips
    );
}

/** A resolver that fails the test if it is ever called (the URL must be rejected before DNS). */
function forbiddenDns(): Closure
{
    return fn (string $host) => throw new RuntimeException('DNS must not be queried for this input.');
}

beforeEach(function () {
    config()->set('integrations.fetcher.allowed_hosts', ['approved.example.com']);
    $this->validator = new SafeUrlValidator(forbiddenDns());
});

// ---- Lab tests ---------------------------------------------------------------------------------

test('HTTP URLs are rejected', function () {
    expect(fn () => $this->validator->validate('http://approved.example.com'))
        ->toThrow(InvalidArgumentException::class);
});

test('loopback destinations are rejected', function () {
    expect(fn () => $this->validator->validate('https://127.0.0.1'))
        ->toThrow(InvalidArgumentException::class);
});

test('unapproved hostnames are rejected', function () {
    expect(fn () => $this->validator->validate('https://unapproved.example.org'))
        ->toThrow(InvalidArgumentException::class);
});

test('URL userinfo is rejected', function () {
    expect(fn () => $this->validator->validate('https://user:password@approved.example.com'))
        ->toThrow(InvalidArgumentException::class);
});

test('nonstandard HTTPS ports are rejected', function () {
    expect(fn () => $this->validator->validate('https://approved.example.com:8443'))
        ->toThrow(InvalidArgumentException::class);
});

test('file URLs are rejected', function () {
    expect(fn () => $this->validator->validate('file:///etc/hosts'))
        ->toThrow(InvalidArgumentException::class);
});

// ---- Extra coverage: malformed input (rejected before any DNS lookup) --------------------------------

test('malformed and unsafe URL syntax is rejected', function (string $url) {
    expect(fn () => $this->validator->validate($url))->toThrow(InvalidArgumentException::class);
})->with([
    'empty' => '',
    'malformed scheme' => 'htp://approved.example.com',
    'no scheme' => '//approved.example.com',
    'no host' => 'https:///path',
    'userinfo trick' => 'https://approved.example.com@localhost/',
    'fragment' => 'https://approved.example.com/#frag',
    'space in url' => 'https://approved.example.com/a b',
    'newline in url' => "https://approved.example.com/\nHost: evil",
    'ipv6 literal' => 'https://[::1]/',
    'decimal ip' => 'https://2130706433/',
    'single label host' => 'https://localhost/',
    'trailing dot host' => 'https://approved.example.com./',
]);

test('an over-long URL is rejected', function () {
    expect(fn () => $this->validator->validate('https://approved.example.com/'.str_repeat('a', 2100)))
        ->toThrow(InvalidArgumentException::class);
});

test('the explicit default port 443 is allowed', function () {
    $validator = new SafeUrlValidator(fixedDns(['93.184.216.34']));

    expect($validator->validate('https://approved.example.com:443/x')['host'])->toBe('approved.example.com');
});

// ---- Extra coverage: DNS results -----------------------------------------------------------------------

test('a public address is accepted and the host is lowercased', function () {
    $validator = new SafeUrlValidator(fixedDns(['93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946']));

    $result = $validator->validate('HTTPS://Approved.Example.COM/path?x=1');

    expect($result['host'])->toBe('approved.example.com')
        ->and($result['addresses'])->toBe(['93.184.216.34', '2606:2800:220:1:248:1893:25c8:1946']);
});

test('non-public addresses returned by DNS are rejected', function (string $ip) {
    $validator = new SafeUrlValidator(fixedDns([$ip]));

    expect(fn () => $validator->validate('https://approved.example.com'))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'private 10/8' => '10.0.0.5',
    'loopback' => '127.0.0.1',
    'cloud metadata / link-local' => '169.254.169.254',
    'private 172.16/12' => '172.16.5.1',
    'private 192.168/16' => '192.168.1.10',
    'carrier-grade NAT' => '100.64.0.1',
    'unspecified' => '0.0.0.0',
    'multicast' => '224.0.0.1',
    'benchmarking' => '198.18.0.1',
    'ipv6 loopback' => '::1',
    'ipv6 link-local' => 'fe80::1',
    'ipv6 unique local' => 'fc00::1',
    'ipv4-mapped loopback' => '::ffff:127.0.0.1',
    'ipv4-mapped private' => '::ffff:10.0.0.1',
    'ipv6 documentation' => '2001:db8::1',
]);

test('mixed public and private DNS results are rejected', function () {
    $validator = new SafeUrlValidator(fixedDns(['93.184.216.34', '10.0.0.5']));

    expect(fn () => $validator->validate('https://approved.example.com'))
        ->toThrow(InvalidArgumentException::class);
});

test('DNS failure or an empty answer is rejected', function () {
    foreach ([fn () => false, fn () => []] as $resolver) {
        $validator = new SafeUrlValidator($resolver);

        expect(fn () => $validator->validate('https://approved.example.com'))
            ->toThrow(InvalidArgumentException::class);
    }
});

test('CNAME entries are skipped but an address is still required', function () {
    $withAddress = new SafeUrlValidator(fn () => [
        ['type' => 'CNAME', 'target' => 'edge.example.net'],
        ['type' => 'A', 'ip' => '93.184.216.34'],
    ]);
    $aliasOnly = new SafeUrlValidator(fn () => [['type' => 'CNAME', 'target' => 'edge.example.net']]);

    expect($withAddress->validate('https://approved.example.com')['addresses'])->toBe(['93.184.216.34'])
        ->and(fn () => $aliasOnly->validate('https://approved.example.com'))->toThrow(InvalidArgumentException::class);
});
