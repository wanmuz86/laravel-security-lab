<?php

return [
    'fetcher' => [
        'allowed_hosts' => array_filter(
            array_map(
                'trim',
                explode(',', env('FETCH_ALLOWED_HOSTS', ''))
            )
        ),
        'connect_timeout' => 3,
        'timeout' => 8,
        'max_response_bytes' => 1048576,
    ],
    'webhook' => [
        'secret' => env('WEBHOOK_SHARED_SECRET'),
        'tolerance_seconds' => 300,
    ],
];
