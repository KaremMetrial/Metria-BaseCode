<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation\Providers;

use App\Infrastructure\Translation\Contracts\TranslationProviderInterface;
use App\Infrastructure\Translation\DTOs\ProviderHealth;
use App\Infrastructure\Translation\Enums\ProviderState;
use App\Infrastructure\Translation\Exceptions\ProviderUnavailableException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Decorator that wraps any TranslationProviderInterface with circuit breaker protection.
 */
class CircuitBreakerProvider implements TranslationProviderInterface
{
    public function __construct(
        private readonly TranslationProviderInterface $inner,
        private readonly int $failureThreshold = 5,
        private readonly int $cooldownSeconds = 60
    ) {}

    public function translate(array $values, string $sourceLocale, string $targetLocale): array
    {
        $state = $this->getCircuitState();
        if ($state === 'open') {
            throw new ProviderUnavailableException("Circuit breaker is OPEN for provider [{$this->name()}]. Calls blocked.");
        }

        try {
            $result = $this->inner->translate($values, $sourceLocale, $targetLocale);
            $this->recordSuccess();

            return $result;
        } catch (Throwable $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    public function supports(string $sourceLocale, string $targetLocale): bool
    {
        return $this->inner->supports($sourceLocale, $targetLocale);
    }

    public function health(): ProviderHealth
    {
        $state = $this->getCircuitState();

        if ($state === 'open') {
            $openUntil = Cache::get("translation:cb:open_until:{$this->name()}");
            $retryAfter = $openUntil ? Carbon::createFromTimestamp($openUntil) : null;

            return new ProviderHealth(
                ProviderState::Offline,
                "Circuit breaker is OPEN for [{$this->name()}] due to consecutive failures.",
                $retryAfter
            );
        }

        if ($state === 'half_open') {
            return new ProviderHealth(
                ProviderState::Degraded,
                "Circuit breaker is in HALF_OPEN state for [{$this->name()}]. Cooldown completed, awaiting next request."
            );
        }

        return $this->inner->health();
    }

    public function name(): string
    {
        return $this->inner->name();
    }

    private function getCircuitState(): string
    {
        $state = Cache::get("translation:cb:state:{$this->name()}", 'closed');
        if ($state === 'open') {
            $openUntil = Cache::get("translation:cb:open_until:{$this->name()}");
            if ($openUntil && now()->timestamp > $openUntil) {
                Cache::put("translation:cb:state:{$this->name()}", 'half_open');

                return 'half_open';
            }
        }

        return $state;
    }

    private function recordSuccess(): void
    {
        Cache::forget("translation:cb:failures:{$this->name()}");
        Cache::put("translation:cb:state:{$this->name()}", 'closed');
    }

    private function recordFailure(): void
    {
        $failures = Cache::get("translation:cb:failures:{$this->name()}", 0) + 1;
        Cache::put("translation:cb:failures:{$this->name()}", $failures, 3600);

        if ($failures >= $this->failureThreshold) {
            Cache::put("translation:cb:state:{$this->name()}", 'open');
            Cache::put("translation:cb:open_until:{$this->name()}", now()->addSeconds($this->cooldownSeconds)->timestamp);
        }
    }
}
