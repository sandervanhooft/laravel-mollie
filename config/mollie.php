<?php

declare(strict_types=1);

use Mollie\Laravel\MollieLaravelHttpClientAdapter;

return [

    /*
     * Your Mollie API key.
     *
     * Use your test or live API key as provided by Mollie. This will be used
     * by the default facade `Mollie::api()` unless you override it on the client manually.
     */
    'key' => env('MOLLIE_KEY', 'test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'),

    /*
     * HTTP client settings used by the Mollie API client.
     */
    'http' => [
        /*
         * The HTTP adapter class to use.
         *
         * Provide a fully qualified class name. The class is expected to be compatible
         * with Mollie's PHP SDK HTTP adapter expectations; typically you extend
         * `\Mollie\Laravel\MollieLaravelHttpClientAdapter` to customize behavior.
         */
        'adapter' => env('MOLLIE_HTTP_ADAPTER', MollieLaravelHttpClientAdapter::class),

        /*
         * Optional automatic retry behavior for transient network issues.
         */
        'retry' => [
            /*
             * Number of retry attempts OR an array of backoff intervals in milliseconds.
             * Set to 0 to disable retries.
             *
             * Examples:
             * - integer: 3
             * - array:   [100, 200, 400]  // sleep per attempt in ms
             */
            'times' => env('MOLLIE_HTTP_RETRY_TIMES', MollieLaravelHttpClientAdapter::DEFAULT_RETRY_TIMES),

            /*
             * Sleep in milliseconds between attempts when 'times' is an integer.
             * Ignored when 'times' is an array.
             */
            'sleep_ms' => env('MOLLIE_HTTP_RETRY_SLEEP_MS', MollieLaravelHttpClientAdapter::DEFAULT_RETRY_SLEEP_MS),
        ],

        /*
         * The maximum number of seconds to wait for a response.
         * If exceeded, an Illuminate\Http\Client\ConnectionException will be thrown.
         *
         * Laravel default is 30 seconds.
         */
        'timeout' => env('MOLLIE_HTTP_TIMEOUT', MollieLaravelHttpClientAdapter::DEFAULT_TIMEOUT),

        /*
         * The maximum number of seconds to wait while trying to connect to the server.
         *
         * Laravel default is 5 seconds.
         */
        'connect_timeout' => env('MOLLIE_HTTP_CONNECT_TIMEOUT', MollieLaravelHttpClientAdapter::DEFAULT_CONNECT_TIMEOUT),
    ],

    /*
     * If you intend on using Mollie Connect, place the following in 'config/services.php':
     *
     * 'mollie' => [
     *     'client_id'     => env('MOLLIE_CLIENT_ID', 'app_xxx'),
     *     'client_secret' => env('MOLLIE_CLIENT_SECRET'),
     *     'redirect'      => env('MOLLIE_REDIRECT_URI'),
     * ],
     */

];
