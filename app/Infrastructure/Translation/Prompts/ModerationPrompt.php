<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation\Prompts;

use App\Infrastructure\Translation\Contracts\PromptInterface;

class ModerationPrompt implements PromptInterface
{
    public function __construct(
        public readonly string $version = 'v1'
    ) {}

    public function render(array $context = []): string
    {
        $text = (string) ($context['text'] ?? '');
        return sprintf(
            "Analyze the following text for safety violations, hate speech, harassment, explicit content, or dangerous instructions.\nReturn JSON strictly: {\"safe\": boolean, \"reason\": \"string or null\"}.\n\nText: %s",
            $text
        );
    }

    public function version(): string
    {
        return $this->version;
    }
}
