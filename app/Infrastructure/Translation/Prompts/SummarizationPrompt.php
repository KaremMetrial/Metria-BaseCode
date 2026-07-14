<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation\Prompts;

use App\Infrastructure\Translation\Contracts\PromptInterface;

class SummarizationPrompt implements PromptInterface
{
    public function __construct(
        public readonly string $version = 'v1',
        public readonly int $maxWords = 100
    ) {}

    public function render(array $context = []): string
    {
        $text = (string) ($context['text'] ?? '');
        return sprintf(
            "Summarize the following text concisely in at most %d words:\n\n%s",
            $this->maxWords,
            $text
        );
    }

    public function version(): string
    {
        return $this->version;
    }
}
