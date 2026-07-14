<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation;

use App\Infrastructure\Translation\Contracts\TranslationProviderInterface;
use App\Infrastructure\Translation\DTOs\ProviderHealth;

class FluentTranslator implements TranslationProviderInterface
{
    private string $fromLocale;

    private string $toLocale;

    public function __construct(
        public readonly TranslationProviderInterface $provider,
        private readonly TranslationManager $manager
    ) {
        $this->fromLocale = app()->getLocale();
        $fallback = config('localization.fallback', 'en');
        $this->toLocale = is_string($fallback) ? $fallback : 'en';
    }

    /**
     * Define the source locale.
     */
    public function from(string $locale): self
    {
        $this->fromLocale = $locale;

        return $this;
    }

    /**
     * Define the target locale.
     */
    public function to(string $locale): self
    {
        $this->toLocale = $locale;

        return $this;
    }

    /**
     * Translate the values. Supports both the fluent API signature and direct provider interface.
     *
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    public function translate(array $values, ?string $sourceLocale = null, ?string $targetLocale = null): array
    {
        if ($sourceLocale !== null && $targetLocale !== null) {
            return $this->provider->translate($values, $sourceLocale, $targetLocale);
        }

        return $this->manager->executeTranslation(
            $this->provider,
            $values,
            $this->fromLocale,
            $this->toLocale
        );
    }

    /**
     * Supports implementation from TranslationProviderInterface.
     */
    public function supports(string $sourceLocale, string $targetLocale): bool
    {
        return $this->provider->supports($sourceLocale, $targetLocale);
    }

    /**
     * Health status from TranslationProviderInterface.
     */
    public function health(): ProviderHealth
    {
        return $this->provider->health();
    }

    /**
     * Provider name from TranslationProviderInterface.
     */
    public function name(): string
    {
        return $this->provider->name();
    }

    /**
     * Dynamically delegate calls to the underlying provider.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->provider->{$method}(...$parameters);
    }
}
