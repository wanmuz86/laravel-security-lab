<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use InvalidArgumentException;

class SafeUrlValidator
{
    /**
     * Special-purpose ranges that PHP's FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE do not all cover
     * (for example carrier-grade NAT 100.64.0.0/10, benchmarking 198.18.0.0/15, documentation ranges, multicast,
     * NAT64, Teredo and 6to4). A production system should use a maintained IP classification library instead.
     */
    private const BLOCKED_RANGES = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
        '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
        '::/128', '::1/128', '64:ff9b::/96', '100::/64', '2001::/32', '2001:db8::/32',
        '2002::/16', 'fc00::/7', 'fe80::/10', 'ff00::/8',
    ];

    /** @var Closure(string): (array<int, array<string, mixed>>|false) */
    private Closure $resolver;

    /**
     * @param  Closure|null  $resolver  Optional DNS resolver (host => dns_get_record-style records).
     *                                  Tests inject one so they never depend on real DNS.
     */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver ?? static fn (string $host) => @dns_get_record($host, DNS_A | DNS_AAAA);
    }

    public function validate(string $url): array
    {
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7F]/', $url)) {
            throw new InvalidArgumentException('Invalid URL.');
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            throw new InvalidArgumentException('Invalid URL.');
        }

        if (
            strtolower($parts['scheme'] ?? '') !== 'https' ||
            isset($parts['user']) ||
            isset($parts['pass']) ||
            isset($parts['fragment']) ||
            (isset($parts['port']) && $parts['port'] !== 443)
        ) {
            throw new InvalidArgumentException('URL destination is not permitted.');
        }

        $host = strtolower($parts['host']);

        // Restrict this exercise to ASCII DNS hostnames.
        if (
            strlen($host) > 253 ||
            ! preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/', $host) ||
            filter_var($host, FILTER_VALIDATE_IP)
        ) {
            throw new InvalidArgumentException('Invalid hostname.');
        }

        $allowed = array_map('strtolower', config('integrations.fetcher.allowed_hosts', []));

        if (! in_array($host, $allowed, true)) {
            throw new InvalidArgumentException('Hostname is not allowlisted.');
        }

        $records = ($this->resolver)($host);

        if ($records === false || $records === []) {
            throw new InvalidArgumentException('DNS resolution failed.');
        }

        $addresses = [];

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            // Alias (CNAME) entries carry no address; only A/AAAA entries are connection targets.
            if ($ip === null) {
                continue;
            }

            if (! $this->isPublicIp($ip)) {
                throw new InvalidArgumentException('Unsafe destination address.');
            }

            $addresses[] = $ip;
        }

        if ($addresses === []) {
            throw new InvalidArgumentException('DNS resolution failed.');
        }

        return [
            'host' => $host,
            'addresses' => array_values(array_unique($addresses)),
        ];
    }

    private function isPublicIp(string $ip): bool
    {
        $packed = @inet_pton($ip);

        if ($packed === false) {
            return false;
        }

        // Unwrap IPv4-mapped IPv6 (::ffff:a.b.c.d) so ::ffff:127.0.0.1 is judged as 127.0.0.1.
        if (strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            $packed = substr($packed, 12);
            $ip = (string) inet_ntop($packed);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        foreach (self::BLOCKED_RANGES as $cidr) {
            if ($this->inCidr($packed, $cidr)) {
                return false;
            }
        }

        return true;
    }

    private function inCidr(string $packed, string $cidr): bool
    {
        [$network, $bits] = explode('/', $cidr);
        $networkBin = (string) inet_pton($network);

        if (strlen($networkBin) !== strlen($packed)) {
            return false;
        }

        $bits = (int) $bits;
        $fullBytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($fullBytes > 0 && substr($packed, 0, $fullBytes) !== substr($networkBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($packed[$fullBytes]) & $mask) === (ord($networkBin[$fullBytes]) & $mask);
    }
}
