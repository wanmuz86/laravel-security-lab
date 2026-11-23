<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class SecureHttpFetcher
{
    public function __construct(
        private SafeUrlValidator $validator
    ) {}

    public function fetch(string $url): array
    {
        $destination = $this->validator->validate($url);
        $maxBytes = (int) config('integrations.fetcher.max_response_bytes', 1048576);
        $body = '';
        $tooLarge = false;

        $curl = curl_init();

        if ($curl === false) {
            throw new RuntimeException('Unable to initialize HTTP client.');
        }

        $host = $destination['host'];
        $ip = $destination['addresses'][0];

        // CURLOPT_RESOLVE expects bracketed IPv6 addresses.
        $pinnedIp = str_contains($ip, ':') ? "[{$ip}]" : $ip;

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => (int) config('integrations.fetcher.connect_timeout', 3),
            CURLOPT_TIMEOUT => (int) config('integrations.fetcher.timeout', 8),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROXY => '',
            CURLOPT_RESOLVE => [
                "{$host}:443:{$pinnedIp}",
            ],
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_USERAGENT => 'SecureLaravelLab/1.0',
            CURLOPT_WRITEFUNCTION => function ($handle, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    $tooLarge = true;

                    return 0;
                }

                $body .= $chunk;

                return strlen($chunk);
            },
        ]);

        try {
            $success = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

            if ($tooLarge) {
                throw new RuntimeException('Response exceeds permitted size.');
            }

            if ($success === false) {
                throw new RuntimeException('Outbound request failed.');
            }

            if ($status >= 300 && $status < 400) {
                throw new RuntimeException('Redirects are not permitted.');
            }

            if ($status < 200 || $status >= 300) {
                throw new RuntimeException('Upstream returned an unsuccessful response.');
            }

            return [
                'status' => $status,
                'body' => $body,
            ];
        } finally {
            curl_close($curl);
        }
    }
}
