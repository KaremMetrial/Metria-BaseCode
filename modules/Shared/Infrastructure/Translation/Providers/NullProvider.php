<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Translation\Providers;

use Modules\Shared\Infrastructure\Translation\Contracts\TranslationProviderInterface;
use Modules\Shared\Infrastructure\Translation\DTOs\ProviderHealth;

class NullProvider implements TranslationProviderInterface
{
    public function translate(array $values, string $sourceLocale, string $targetLocale): array
    {
        // Null provider returns the original input unchanged (fail-safe)
        return $values;
    }

    public function supports(string $sourceLocale, string $targetLocale): bool
    {
        return true;
    }

    public function health(): ProviderHealth
    {
        return ProviderHealth::healthy();
    }

    public function name(): string
    {
        return 'null';
    }
}
