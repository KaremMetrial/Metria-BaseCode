<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation\Enums;

enum ProviderState: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Offline = 'offline';
    case RateLimited = 'rate_limited';
}
