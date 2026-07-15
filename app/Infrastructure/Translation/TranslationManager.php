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
        $defaultVal = $this->config->get('translation.default', 'gemini');
        return is_string($defaultVal) ? $defaultVal : 'gemini';
    }

    protected function createGeminiDriver(): TranslationProviderInterface
    {
        $key = $this->config->get('translation.providers.gemini.key');
        $model = $this->config->get('translation.providers.gemini.model', 'gemini-1.5-flash');
        return new GeminiTranslationProvider(
            is_string($key) ? $key : null,
            is_string($model) ? $model : 'gemini-1.5-flash'
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
        $driverStr = is_string($driver) ? $driver : null;
        $provider = parent::driver($driverStr);

        if (! $provider instanceof TranslationProviderInterface) {
            throw new \RuntimeException(__('translations.driver_interface_required'));
        }


        if (! $provider instanceof \App\Infrastructure\Translation\Providers\CircuitBreakerProvider) {
            $thresholdVal = $this->config->get('translation.circuit_breaker.failure_threshold', 5);
            $threshold = is_numeric($thresholdVal) ? (int) $thresholdVal : 5;
            $cooldownVal = $this->config->get('translation.circuit_breaker.cooldown_seconds', 60);
            $cooldown = is_numeric($cooldownVal) ? (int) $cooldownVal : 60;
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
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    public function executeTranslation(
        TranslationProviderInterface $provider,
        array $values,
        string $sourceLocale,
        string $targetLocale
    ): array {
        if (! $this->config->get('translation.enabled', true)) {
            $result = [];
            foreach ($values as $key => $val) {
                $result[(string) $key] = is_scalar($val) ? (string) $val : '';
            }
            return $result;
        }

        if (empty($values)) {
            return [];
        }

        $translated = [];
        $missing = [];

        foreach ($values as $key => $value) {
            $strValue = is_scalar($value) ? (string) $value : '';
            if (trim($strValue) === '') {
                $translated[$key] = '';

                continue;
            }

            // Canonical Unicode & Whitespace Normalization
            $normalizedValue = normalizer_normalize($strValue, \Normalizer::FORM_C);
            if ($normalizedValue === false) {
                $normalizedValue = $strValue;
            }
            $preg = preg_replace('/\s+/', ' ', $normalizedValue);
            $normalizedValue = trim(is_string($preg) ? $preg : $normalizedValue);
            $hash = hash('sha256', $normalizedValue);

            $promptVersionVal = $this->config->get("translation.providers.{$provider->name()}.prompt_version", 'v1');
            $promptVersion = is_string($promptVersionVal) ? $promptVersionVal : 'v1';
            $cacheKey = "translation:{$provider->name()}:{$sourceLocale}:{$targetLocale}:{$promptVersion}:{$hash}";

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $translated[$key] = is_scalar($cached) ? (string) $cached : '';
            } else {
                $missing[$key] = $strValue;
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
            $preg = preg_replace('/\s+/', ' ', $normalizedValue);
            $normalizedValue = trim(is_string($preg) ? $preg : $normalizedValue);
            $hash = hash('sha256', $normalizedValue);

            $promptVersionVal = $this->config->get("translation.providers.{$provider->name()}.prompt_version", 'v1');
            $promptVersion = is_string($promptVersionVal) ? $promptVersionVal : 'v1';
            $cacheKey = "translation:{$provider->name()}:{$sourceLocale}:{$targetLocale}:{$promptVersion}:{$hash}";

            $ttlVal = $this->config->get('translation.cache_ttl', 2592000);
            $ttl = is_numeric($ttlVal) ? (int) $ttlVal : 2592000;
            Cache::put($cacheKey, $translatedValue, $ttl);

            $translated[$key] = (string) $translatedValue;
        }

        return $translated;
    }

    /**
     * Attempt translation with fallback provider chaining.
     *
     * @param  array<string, string>  $values
     * @return array<string, string>
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
            $fallbacksArray = is_array($fallbacks) ? array_filter($fallbacks, 'is_string') : [];
            foreach ($fallbacksArray as $fallbackName) {
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
