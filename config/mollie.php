<?php

declare(strict_types=1);

return [

    'key' => env('MOLLIE_KEY', 'test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'),

    // Optional HTTP client retry behaviour for transient network issues
    'http' => [
        'retry' => [
            // Number of retry attempts OR an array of backoff intervals in milliseconds (0 disables retries)
            // Examples:
            // - int:    3
            // - array:  [100, 200, 400]  // sleep per attempt in ms
            'times' => env('MOLLIE_HTTP_RETRY_TIMES', 0),
            // Sleep in milliseconds between attempts (only used when 'times' is an int)
            'sleep_ms' => env('MOLLIE_HTTP_RETRY_SLEEP_MS', 100),
        ],
    ],

    // If you intend on using Mollie Connect, place the following in the 'config/services.php'
    // 'mollie' => [
    //     'client_id'     => env('MOLLIE_CLIENT_ID', 'app_xxx'),
    //     'client_secret' => env('MOLLIE_CLIENT_SECRET'),
    //     'redirect'      => env('MOLLIE_REDIRECT_URI'),
    // ],

];
