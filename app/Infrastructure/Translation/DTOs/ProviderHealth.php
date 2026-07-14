<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation\DTOs;

use App\Infrastructure\Translation\Enums\ProviderState;
use Carbon\Carbon;

class ProviderHealth
{
    public function __construct(
        public readonly ProviderState $state,
        public readonly ?string $reason = null,
        public readonly ?Carbon $retryAfter = null
    ) {}

    public static function healthy(): self
    {
        return new self(ProviderState::Healthy);
    }

    public static function offline(string $reason): self
    {
        return new self(ProviderState::Offline, $reason);
    }

    public static function rateLimited(string $reason, ?Carbon $retryAfter = null): self
    {
        return new self(ProviderState::RateLimited, $reason, $retryAfter);
    }

    public static function degraded(string $reason): self
    {
        return new self(ProviderState::Degraded, $reason);
    }
}
