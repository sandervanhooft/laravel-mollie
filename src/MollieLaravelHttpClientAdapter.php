<?php

declare(strict_types=1);

namespace Mollie\Laravel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest as LaravelPendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Mollie\Api\Contracts\HttpAdapterContract;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\RetryableNetworkRequestException;
use Mollie\Api\Http\PendingRequest;
use Mollie\Api\Http\Response;
use Mollie\Api\Traits\HasDefaultFactories;

class MollieLaravelHttpClientAdapter implements HttpAdapterContract
{
    use HasDefaultFactories;

    /**
     * Get the version string for this HTTP adapter.
     */
    public function version(): ?string
    {
        return 'Laravel/HttpClient';
    }

    /**
     * Send a request to the specified Mollie API URL.
     *
     * @throws ApiException
     */
    public function sendRequest(PendingRequest $pendingRequest): Response
    {
        $psrRequest = $pendingRequest->createPsrRequest();

        try {
            // Build the Laravel HTTP client with optional retries
            $http = $this->makeHttpClient($pendingRequest, $psrRequest->getBody());

            $response = $http->send(
                $pendingRequest->method(),
                $pendingRequest->url(),
            );

            $psrResponse = $response->toPsrResponse();

            return new Response($psrResponse, $psrRequest, $pendingRequest);
        } catch (ConnectionException $e) {
            throw new RetryableNetworkRequestException($pendingRequest, $e->getMessage(), $e);
        } catch (RequestException $e) {
            // RequestExceptions without response are handled by the retryable network request exception
            return new Response($e->response->toPsrResponse(), $psrRequest, $pendingRequest, $e);
        }
    }

    /**
     * Create a configured Laravel HTTP client for the given request, applying headers, query, body and optional retries.
     */
    private function makeHttpClient(PendingRequest $pendingRequest, $body): LaravelPendingRequest
    {
        $http = Http::withHeaders($pendingRequest->headers()->all())
            ->withUrlParameters($pendingRequest->query()->all())
            ->withBody($body);

        [$times, $sleepMs] = $this->getRetryConfig();
        if (is_array($times) && ! empty($times)) {
            // Laravel supports passing an array of backoff intervals (ms)
            $http = $http->retry($times);
        } elseif (is_int($times) && $times > 0) {
            $http = $http->retry($times, $sleepMs);
        }

        return $http;
    }

    /**
     * Read retry configuration from config.
     *
     * @return array{0:int|array<int,int>,1:int} [times(int or array of ms), sleep_ms]
     */
    private function getRetryConfig(): array
    {
        $configuredTimes = config('mollie.http.retry.times', 0);

        // Normalize: allow int or array<int,int>
        if (is_array($configuredTimes)) {
            $times = array_values(array_map('intval', $configuredTimes));
        } else {
            $times = (int) $configuredTimes;
        }

        $sleepMs = (int) config('mollie.http.retry.sleep_ms', 100);

        return [$times, $sleepMs];
    }
}

