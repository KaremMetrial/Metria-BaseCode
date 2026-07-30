<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Translation\Providers;

use Modules\Shared\Infrastructure\Translation\Contracts\TranslationProviderInterface;
use Modules\Shared\Infrastructure\Translation\DTOs\ProviderHealth;
use Illuminate\Support\Facades\Log;

class LoggingProvider implements TranslationProviderInterface
{
    public function translate(array $values, string $sourceLocale, string $targetLocale): array
    {
        Log::info('[Translation LoggingProvider] Translating payload:', [
            'source' => $sourceLocale,
            'target' => $targetLocale,
            'payload' => $values,
        ]);

        $translated = [];
        foreach ($values as $key => $value) {
            $translated[$key] = "[{$targetLocale}] {$value}";
        }

        return $translated;
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
        return 'logging';
    }
}
