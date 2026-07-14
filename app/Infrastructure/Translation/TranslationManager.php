<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation;

use App\Infrastructure\Translation\Contracts\TranslationProviderInterface;
use App\Infrastructure\Translation\Enums\ProviderState;
use App\Infrastructure\Translation\Exceptions\ProviderUnavailableException;
use App\Infrastructure\Translation\Exceptions\RateLimitedException;
use App\Infrastructure\Translation\Providers\GeminiTranslationProvider;
use App\Infrastructure\Translation\Providers\LoggingProvider;
use App\Infrastructure\Translation\Providers\NullProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Manager;

class TranslationManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('translation.default', 'gemini');
    }

    protected function createGeminiDriver(): TranslationProviderInterface
    {
        return new GeminiTranslationProvider(
            $this->config->get('translation.providers.gemini.key'),
            $this->config->get('translation.providers.gemini.model', 'gemini-1.5-flash')
        );
    }

    protected function createLoggingDriver(): TranslationProviderInterface
    {
        return new LoggingProvider;
    }

    protected function createNullDriver(): TranslationProviderInterface
    {
        return new NullProvider;
    }

    /**
     * Get a driver instance wrapped in FluentTranslator.
     *
     * @param  string|null  $driver
     * @return FluentTranslator
     */
    public function driver($driver = null)
    {
        $provider = parent::driver($driver);

        if (! $provider instanceof \App\Infrastructure\Translation\Providers\CircuitBreakerProvider) {
            $threshold = (int) $this->config->get('translation.circuit_breaker.failure_threshold', 5);
            $cooldown = (int) $this->config->get('translation.circuit_breaker.cooldown_seconds', 60);
            $provider = new \App\Infrastructure\Translation\Providers\CircuitBreakerProvider($provider, $threshold, $cooldown);
        }

        return new FluentTranslator($provider, $this);
    }

    /**
     * Start a fluent translation query.
     */
    public function from(string $locale): FluentTranslator
    {
        return $this->driver()->from($locale);
    }

    /**
     * Start a fluent translation query.
     */
    public function to(string $locale): FluentTranslator
    {
        return $this->driver()->to($locale);
    }

    /**
     * Execute direct translation.
     *
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    public function translate(array $values): array
    {
        return $this->driver()->translate($values);
    }

    /**
     * Core translation orchestrator. Handles caching, Unicode normalization, and fallback chains.
     *
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    public function executeTranslation(
        TranslationProviderInterface $provider,
        array $values,
        string $sourceLocale,
        string $targetLocale
    ): array {
        if (! $this->config->get('translation.enabled', true)) {
            return $values;
        }

        if (empty($values)) {
            return [];
        }

        $translated = [];
        $missing = [];

        foreach ($values as $key => $value) {
            if ($value === null || trim((string) $value) === '') {
                $translated[$key] = '';

                continue;
            }

            // Canonical Unicode & Whitespace Normalization
            $normalizedValue = normalizer_normalize((string) $value, \Normalizer::FORM_C);
            if ($normalizedValue === false) {
                $normalizedValue = (string) $value;
            }
            $normalizedValue = trim((string) preg_replace('/\s+/', ' ', $normalizedValue));
            $hash = hash('sha256', $normalizedValue);

            $promptVersion = $this->config->get("translation.providers.{$provider->name()}.prompt_version", 'v1');
            $cacheKey = "translation:{$provider->name()}:{$sourceLocale}:{$targetLocale}:{$promptVersion}:{$hash}";

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $translated[$key] = $cached;
            } else {
                $missing[$key] = (string) $value;
            }
        }

        if (empty($missing)) {
            return $translated;
        }

        // Send remaining values to the provider with fallbacks
        $apiResults = $this->translateWithFallback($provider, $missing, $sourceLocale, $targetLocale);

        // Cache the new results
        foreach ($apiResults as $key => $translatedValue) {
            $originalValue = $missing[$key];
            $normalizedValue = normalizer_normalize($originalValue, \Normalizer::FORM_C);
            if ($normalizedValue === false) {
                $normalizedValue = $originalValue;
            }
            $normalizedValue = trim((string) preg_replace('/\s+/', ' ', $normalizedValue));
            $hash = hash('sha256', $normalizedValue);

            $promptVersion = $this->config->get("translation.providers.{$provider->name()}.prompt_version", 'v1');
            $cacheKey = "translation:{$provider->name()}:{$sourceLocale}:{$targetLocale}:{$promptVersion}:{$hash}";

            Cache::put($cacheKey, $translatedValue, $this->config->get('translation.cache_ttl', 2592000));

            $translated[$key] = $translatedValue;
        }

        return $translated;
    }

    /**
     * Attempt translation with fallback provider chaining.
     */
    private function translateWithFallback(
        TranslationProviderInterface $provider,
        array $values,
        string $sourceLocale,
        string $targetLocale
    ): array {
        try {
            return $provider->translate($values, $sourceLocale, $targetLocale);
        } catch (RateLimitedException|ProviderUnavailableException $e) {
            // Check fallback providers from configuration
            $fallbacks = $this->config->get('translation.fallbacks', []);
            foreach ($fallbacks as $fallbackName) {
                if ($fallbackName === $provider->name()) {
                    continue;
                }

                try {
                    $fallbackProvider = $this->driver($fallbackName);
                    if ($fallbackProvider->supports($sourceLocale, $targetLocale) &&
                        $fallbackProvider->health()->state !== ProviderState::Offline) {
                        Log::warning("Translation fallback triggered from {$provider->name()} to {$fallbackName}. Error: ".$e->getMessage());

                        return $fallbackProvider->translate($values, $sourceLocale, $targetLocale);
                    }
                } catch (\Throwable $fallbackEx) {
                    Log::error("Translation fallback to {$fallbackName} failed: ".$fallbackEx->getMessage());
                }
            }

            // Re-throw if all fallbacks are exhausted
            throw $e;
        }
    }
}
