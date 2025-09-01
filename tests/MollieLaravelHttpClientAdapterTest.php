<?php

declare(strict_types=1);

namespace Mollie\Laravel\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\RetryableNetworkRequestException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment;
use Mollie\Laravel\MollieLaravelHttpClientAdapter;

/**
 * Class MollieLaravelHttpClientAdapterTest
 */
class MollieLaravelHttpClientAdapterTest extends TestCase
{
    public function test_post_request()
    {
        /** @var MollieApiClient $client */
        $client = app(MollieApiClient::class);
        $payment = new Payment($client);
        $payment->id = uniqid('tr_');
        $payment->redirectUrl = 'https://google.com/redirect';
        $payment->description = 'test';

        Http::fake([
            'https://api.mollie.com/*' => Http::response(json_encode($payment)),
        ]);

        $returnedPayment = $client->payments->create([
            'redirectUrl' => 'https://google.com/redirect',
            'description' => 'test',
            'amount' => [
                'value' => '10.00',
                'currency' => 'EUR',
            ],
        ]);

        $this->assertEquals($payment->id, $returnedPayment->id);
        $this->assertEquals($payment->redirectUrl, $returnedPayment->redirectUrl);
        $this->assertEquals($payment->description, $returnedPayment->description);
    }

    public function test_get_request()
    {
        /** @var MollieApiClient $client */
        $client = app(MollieApiClient::class);
        $payment = new Payment($client);
        $payment->id = uniqid('tr_');
        $payment->redirectUrl = 'https://google.com/redirect';
        $payment->description = 'test';

        Http::fake([
            'https://api.mollie.com/v2/payments/' . $payment->id => Http::response(json_encode($payment)),
        ]);

        $returnedPayment = $client->payments->get($payment->id);

        $this->assertEquals($payment->id, $returnedPayment->id);
        $this->assertEquals($payment->redirectUrl, $returnedPayment->redirectUrl);
        $this->assertEquals($payment->description, $returnedPayment->description);
    }

    public function test_exception_handling()
    {
        /** @var MollieApiClient $client */
        $client = app(MollieApiClient::class);

        // Simulate a network error
        Http::fake([
            'https://api.mollie.com/*' => Http::response('', 500),
        ]);

        $this->expectException(ApiException::class);

        // This should throw an ApiException
        $client->payments->get('non_existing_payment');
    }

    public function test_connection_error_handling()
    {
        /** @var MollieApiClient $client */
        $client = app(MollieApiClient::class);

        // Simulate a connection error
        Http::fake([
            'https://api.mollie.com/*' => function () {
                throw new ConnectionException('Connection error');
            },
        ]);

        $this->expectException(RetryableNetworkRequestException::class);
        $this->expectExceptionMessage('Connection error');

        // This should throw an ApiException with the connection error message
        $client->payments->get('any_payment_id');
    }

    public function test_retries_with_int_times_succeed_after_transient_failures()
    {
        // Enable retries: 3 attempts total (including the first attempt)
        config()->set('mollie.http.retry.times', 3);
        config()->set('mollie.http.retry.sleep_ms', 1);

        /** @var MollieApiClient $client */
        $client = app(MollieApiClient::class);

        $attempts = 0;
        $payment = new Payment($client);
        $payment->id = uniqid('tr_');

        Http::fake([
            'https://api.mollie.com/*' => function () use (&$attempts, $payment) {
                $attempts++;
                // Fail first 2 attempts, succeed on 3rd
                if ($attempts < 3) {
                    throw new ConnectionException('Temporary network issue');
                }

                return Http::response(json_encode($payment), 200);
            },
        ]);

        $returned = $client->payments->get($payment->id);

        $this->assertSame($payment->id, $returned->id);
        // Expect exactly 3 attempts (2 failures + 1 success)
        $this->assertSame(3, $attempts);
    }

    public function test_retries_with_array_backoff_succeed()
    {
        // Array backoff schedule (milliseconds) — Laravel treats this as retry sequence
        config()->set('mollie.http.retry.times', [1, 1, 1]);

        /** @var MollieApiClient $client */
        $client = app(MollieApiClient::class);

        $attempts = 0;
        $payment = new Payment($client);
        $payment->id = uniqid('tr_');

        Http::fake([
            'https://api.mollie.com/*' => function () use (&$attempts, $payment) {
                $attempts++;
                // Fail first 2 attempts, then succeed
                if ($attempts <= 2) {
                    throw new ConnectionException('Temporary network issue');
                }

                return Http::response(json_encode($payment), 200);
            },
        ]);

        $returned = $client->payments->get($payment->id);

        $this->assertSame($payment->id, $returned->id);
        // We expect at least 3 attempts (depending on framework semantics it may be 3 or 4)
        $this->assertGreaterThanOrEqual(3, $attempts);
    }

    public function test_retries_exhausted_throws_retryable_exception()
    {
        config()->set('mollie.http.retry.times', 2);
        config()->set('mollie.http.retry.sleep_ms', 1);

        /** @var MollieApiClient $client */
        $client = app(MollieApiClient::class);

        $attempts = 0;

        Http::fake([
            'https://api.mollie.com/*' => function () use (&$attempts) {
                $attempts++;
                throw new ConnectionException('Still broken');
            },
        ]);

        $this->expectException(RetryableNetworkRequestException::class);

        try {
            $client->payments->get('any');
        } finally {
            // Should attempt at least the configured number of attempts
            $this->assertGreaterThanOrEqual(2, $attempts);
        }
    }

    public function test_can_override_http_adapter_via_config()
    {
        // Ensure flag is reset
        FakeHttpClientAdapter::$constructed = false;

        // Configure the custom adapter
        config()->set('mollie.http.adapter', FakeHttpClientAdapter::class);

        // Ensure the singleton instance is rebuilt with the new config
        app()->forgetInstance(MollieApiClient::class);

        // Resolving the client should construct our fake adapter
        $client = app(MollieApiClient::class);

        $this->assertTrue(FakeHttpClientAdapter::$constructed, 'Configured HTTP adapter was not constructed');
        $this->assertInstanceOf(MollieApiClient::class, $client);
    }
}

// Simple fake adapter that extends the base and flips a flag when constructed
class FakeHttpClientAdapter extends MollieLaravelHttpClientAdapter
{
    public static bool $constructed = false;

    public function __construct()
    {
        // Fake http client adapter, so we're intentionally not calling the parent constructor

        self::$constructed = true;
    }
}
