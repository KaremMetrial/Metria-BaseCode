<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Translation\Prompts;

use Modules\Shared\Infrastructure\Translation\Contracts\PromptInterface;

class ModerationPrompt implements PromptInterface
{
    public function __construct(
        public readonly string $version = 'v1'
    ) {}

    public function render(array $context = []): string
    {
        $textVal = $context['text'] ?? '';
        $text = is_scalar($textVal) ? (string) $textVal : '';
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
