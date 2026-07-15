<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation;

use App\Infrastructure\Translation\Contracts\PromptInterface;
use InvalidArgumentException;

class TranslationPrompt implements PromptInterface
{
    public function __construct(
        public readonly string $version,
        public readonly string $systemPrompt,
        public readonly array $expectedKeys = []
    ) {}

    public function render(array $context = []): string
    {
        return $this->systemPrompt;
    }

    public function version(): string
    {
        return $this->version;
    }

    /**
     * Factory method to generate the appropriate prompt based on version and parameters.
     */
    public static function make(string $version, array $expectedKeys, string $sourceLocale, string $targetLocale): self
    {
        $systemPrompt = match ($version) {
            'v1' => sprintf(
                "You are a professional enterprise translator. Translate the values of the following JSON object from '%s' to '%s'.\n".
                "Rules:\n".
                "- Return ONLY a valid JSON object matching the input structure exactly.\n".
                "- Do NOT change the JSON keys.\n".
                "- All translated values must be non-empty strings.\n".
                "- Preserve all formatting, placeholders, and variables.\n".
                '- Do NOT wrap the JSON in markdown code blocks (e.g. ```json) or include any other text.',
                $sourceLocale,
                $targetLocale
            ),
            default => throw new InvalidArgumentException(__('translations.unknown_prompt_version', ['version' => $version])),
        };


        return new self($version, $systemPrompt, $expectedKeys);
    }
}
