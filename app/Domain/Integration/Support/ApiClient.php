<?php

declare(strict_types=1);

namespace App\Domain\Integration\Support;

use App\Core\Exceptions\IntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Base class for third-party API clients. Gives every integration the same
 * behaviour for free: timeouts, retries with delay, circuit breaking,
 * structured logging, and a single IntegrationException surface.
 *
 * Usage:
 *   class ErpClient extends ApiClient {
 *       protected function service(): string { return 'erp'; }
 *       protected function baseUrl(): string { return config('services.erp.url'); }
 *       public function pushInvoice(array $data): array {
 *           return $this->call('post', '/invoices', $data)->json();
 *       }
 *   }
 */
abstract class ApiClient
{
    abstract protected function service(): string;

    abstract protected function baseUrl(): string;

    /** Override to add auth headers/tokens. */
    protected function configureRequest(PendingRequest $request): PendingRequest
    {
        return $request;
    }

    protected function call(string $method, string $uri, array $data = []): Response
    {
        $breaker = CircuitBreaker::make();

        $response = $breaker->call($this->service(), function () use ($method, $uri, $data) {
            try {
                /** @var Response $response */
                $response = $this->request()->{$method}($uri, $data);
            } catch (ConnectionException $e) {
                Log::warning('integration.connection_failed', [
                    'service' => $this->service(), 'uri' => $uri, 'error' => $e->getMessage(),
                ]);

                throw new IntegrationException(
                    __('integrations.connection_failed', ['service' => $this->service()]),
                    provider: $this->service(),
                );
            }

            if ($response->serverError()) {
                Log::warning('integration.server_error', [
                    'service' => $this->service(), 'uri' => $uri, 'status' => $response->status(),
                ]);

                throw new IntegrationException(
                    __('integrations.service_error', ['service' => $this->service(), 'status' => $response->status()]),
                    provider: $this->service(),
                    context: ['status' => $response->status()],
                );
            }

            return $response;
        });

        if (! $response instanceof Response) {
            throw new IntegrationException(__('integrations.invalid_response_class'), provider: $this->service());
        }


        return $response;
    }

    protected function request(): PendingRequest
    {
        $timeoutVal = config('integrations.http.timeout', 15);
        $timeout = is_numeric($timeoutVal) ? (int) $timeoutVal : 15;

        $retriesVal = config('integrations.http.retries', 2);
        $retries = is_numeric($retriesVal) ? (int) $retriesVal : 2;

        $retryDelayVal = config('integrations.http.retry_delay_ms', 250);
        $retryDelay = is_numeric($retryDelayVal) ? (int) $retryDelayVal : 250;

        return $this->configureRequest(
            Http::baseUrl($this->baseUrl())
                ->acceptJson()
                ->timeout($timeout)
                ->retry(
                    $retries,
                    $retryDelay,
                    throw: false,
                ),
        );
    }
}
