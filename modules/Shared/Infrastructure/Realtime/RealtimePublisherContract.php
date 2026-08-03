<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Realtime;

interface RealtimePublisherContract
{
    /** @param array<string, mixed> $envelope */
    public function publish(array $envelope): void;
}
