<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Translation\Enums;

enum ProviderState: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Offline = 'offline';
    case RateLimited = 'rate_limited';
}
