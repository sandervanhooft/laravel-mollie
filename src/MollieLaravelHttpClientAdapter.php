<?php

declare(strict_types=1);

namespace Mollie\Laravel;

use Illuminate\Http\Client\ConnectionException;
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
            // Build base request
            $http = Http::withHeaders($pendingRequest->headers()->all())
                ->withUrlParameters($pendingRequest->query()->all())
                ->withBody($psrRequest->getBody());

            // Optional retries via config: mollie.http.retry.{times,sleep_ms}
            $times = (int) config('mollie.http.retry.times', 0);
            if ($times > 0) {
                $sleepMs = (int) config('mollie.http.retry.sleep_ms', 100);
                $http = $http->retry($times, $sleepMs);
            }

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
}

