<?php

declare(strict_types=1);

namespace App\Domain\Integration\Support;

use App\Core\Exceptions\IntegrationException;
use Illuminate\Support\Facades\Cache;

/**
 * Minimal cache-backed circuit breaker. After N consecutive failures the
 * circuit opens for the cooldown window and calls fail fast instead of
 * hammering a provider that is already down.
 */
class CircuitBreaker
{
    public function __construct(
        private readonly int $threshold,
        private readonly int $cooldownSeconds,
    ) {}

    public static function make(): self
    {
        return new self(
            (int) config('integrations.circuit_breaker.failure_threshold', 5),
            (int) config('integrations.circuit_breaker.cooldown_seconds', 60),
        );
    }

    public function isOpen(string $service): bool
    {
        return Cache::has($this->openKey($service));
    }

    /** Fail fast when open, otherwise run and record the outcome. */
    public function call(string $service, callable $callback): mixed
    {
        if ($this->isOpen($service)) {
            throw new IntegrationException(
                __('integrations.circuit_open', ['service' => $service]),
                provider: $service,
                context: ['circuit' => 'open'],
            );
        }

        try {
            $result = $callback();
            $this->recordSuccess($service);

            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure($service);

            throw $e;
        }
    }

    public function recordSuccess(string $service): void
    {
        Cache::forget($this->failureKey($service));
    }

    public function recordFailure(string $service): void
    {
        $failures = (int) Cache::increment($this->failureKey($service));

        if ($failures === 1) {
            // First failure sets the counting window.
            Cache::put($this->failureKey($service), 1, $this->cooldownSeconds * 5);
        }

        if ($failures >= $this->threshold) {
            Cache::put($this->openKey($service), true, $this->cooldownSeconds);
            Cache::forget($this->failureKey($service));
        }
    }

    private function failureKey(string $service): string
    {
        return "circuit:{$service}:failures";
    }

    private function openKey(string $service): string
    {
        return "circuit:{$service}:open";
    }
}
