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

    public const DEFAULT_RETRY_TIMES = 3;
    public const DEFAULT_RETRY_SLEEP_MS = 200;
    public const DEFAULT_CONNECT_TIMEOUT = 3.0;
    public const DEFAULT_TIMEOUT = 30.0;

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
            $http = $this->makeHttpClient($pendingRequest, $psrRequest->getBody());

            $http = $this->applyRetryConfig($http);

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
    protected function makeHttpClient(PendingRequest $pendingRequest, $body): LaravelPendingRequest
    {
        return Http::withHeaders($pendingRequest->headers()->all())
            ->withUrlParameters($pendingRequest->query()->all())
            ->timeout((float) config('mollie.http.timeout', self::DEFAULT_TIMEOUT))
            ->connectTimeout((float) config('mollie.http.connect_timeout', self::DEFAULT_CONNECT_TIMEOUT))
            ->withBody($body);
    }

    protected function applyRetryConfig(LaravelPendingRequest $http): LaravelPendingRequest
    {
        $configuredTimes = config('mollie.http.retry.times', self::DEFAULT_RETRY_TIMES);

        // Normalize: allow int or array<int,int>
        if (is_array($configuredTimes)) {
            $times = array_values(array_map('intval', $configuredTimes));
        } else {
            $times = (int) $configuredTimes;
        }

        if (is_array($times) && ! empty($times)) {
            // Laravel supports passing an array of backoff intervals (ms)
            return $http->retry($times);
        }

        if (is_int($times) && $times > 0) {
            return $http->retry($times, (int) config('mollie.http.retry.sleep_ms', self::DEFAULT_RETRY_SLEEP_MS));
        }

        return $http;
    }
}
