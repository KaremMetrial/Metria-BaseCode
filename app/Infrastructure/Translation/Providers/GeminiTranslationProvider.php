<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation\Providers;

use App\Infrastructure\Translation\Contracts\TranslationProviderInterface;
use App\Infrastructure\Translation\DTOs\ProviderHealth;
use App\Infrastructure\Translation\Enums\ProviderState;
use App\Infrastructure\Translation\Exceptions\InvalidTranslationResponseException;
use App\Infrastructure\Translation\Exceptions\ProviderUnavailableException;
use App\Infrastructure\Translation\Exceptions\RateLimitedException;
use App\Infrastructure\Translation\Exceptions\TranslationValidationFailedException;
use App\Infrastructure\Translation\TranslationPrompt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeminiTranslationProvider implements TranslationProviderInterface
{
    public function __construct(
        private readonly ?string $key,
        private readonly string $model
    ) {}

    public function translate(array $values, string $sourceLocale, string $targetLocale): array
    {
        if (empty($this->key)) {
            throw new ProviderUnavailableException('Gemini API key is not configured.');
        }

        $versionVal = config('translation.providers.gemini.prompt_version', 'v1');
        $version = is_string($versionVal) ? $versionVal : 'v1';
        $prompt = TranslationPrompt::make($version, array_keys($values), $sourceLocale, $targetLocale);

        try {
            $timeoutVal = config('translation.timeout', 60);
            $timeout = is_numeric($timeoutVal) ? (int) $timeoutVal : 60;
            $response = Http::timeout($timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->key}", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt->systemPrompt."\nJSON Payload to translate:\n".json_encode($values, JSON_UNESCAPED_UNICODE)],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                    ],
                ]);
        } catch (\Throwable $e) {
            throw new ProviderUnavailableException('Network error contacting Gemini: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            if ($response->status() === 429) {
                $retryAfter = now()->addSeconds(60);
                throw new RateLimitedException('Gemini rate limit exceeded.', 429, null, $retryAfter);
            }
            throw new ProviderUnavailableException('Gemini API returned error code '.$response->status());
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($text)) {
            throw new InvalidTranslationResponseException('Response content is missing or not a string.');
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw new InvalidTranslationResponseException('Response text could not be decoded as valid JSON.');
        }

        $result = [];
        foreach (array_keys($values) as $key) {
            if (! array_key_exists($key, $decoded)) {
                throw new TranslationValidationFailedException("Missing translated key: {$key}");
            }

            $val = $decoded[$key];
            if (! is_string($val)) {
                throw new TranslationValidationFailedException("Translated key '{$key}' value is not a string.");
            }

            if (trim($val) === '') {
                throw new TranslationValidationFailedException("Translated key '{$key}' value is empty.");
            }

            if (! mb_check_encoding($val, 'UTF-8')) {
                throw new TranslationValidationFailedException("Translated key '{$key}' value is not valid UTF-8.");
            }

            $result[(string) $key] = $val;
        }

        return $result;
    }

    public function supports(string $sourceLocale, string $targetLocale): bool
    {
        return true;
    }

    public function health(): ProviderHealth
    {
        if (empty($this->key)) {
            return new ProviderHealth(
                ProviderState::Offline,
                'Gemini API key is unconfigured.'
            );
        }

        return ProviderHealth::healthy();
    }

    public function name(): string
    {
        return 'gemini';
    }
}
