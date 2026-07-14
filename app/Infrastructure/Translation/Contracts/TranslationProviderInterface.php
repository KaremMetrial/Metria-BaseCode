<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation\Contracts;

use App\Infrastructure\Translation\DTOs\ProviderHealth;
use App\Infrastructure\Translation\Exceptions\TranslationException;

interface TranslationProviderInterface
{
    /**
     * Translate an array of key-value text pairs.
     *
     * @param  array<string, string>  $values
     * @return array<string, string> Translated key-value pairs
     *
     * @throws TranslationException
     */
    public function translate(array $values, string $sourceLocale, string $targetLocale): array;

    /**
     * Check if the provider supports translating between the source and target locales.
     */
    public function supports(string $sourceLocale, string $targetLocale): bool;

    /**
     * Get the current health status of the provider.
     */
    public function health(): ProviderHealth;

    /**
     * Get the driver/provider instance name (e.g. 'gemini', 'logging', 'null').
     */
    public function name(): string;
}
