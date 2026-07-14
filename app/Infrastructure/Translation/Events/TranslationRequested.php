<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranslationRequested
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $promptVersion,
        public readonly string $sourceLocale,
        public readonly string $targetLocale,
        public readonly array $values,
        public readonly ?string $correlationId = null
    ) {}
}
