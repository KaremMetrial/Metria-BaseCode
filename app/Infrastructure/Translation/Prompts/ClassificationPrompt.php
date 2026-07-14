<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation\Prompts;

use App\Infrastructure\Translation\Contracts\PromptInterface;

class ClassificationPrompt implements PromptInterface
{
    public function __construct(
        public readonly array $categories = ['support', 'billing', 'general', 'spam'],
        public readonly string $version = 'v1'
    ) {}

    public function render(array $context = []): string
    {
        $text = (string) ($context['text'] ?? '');
        $categoriesList = implode(', ', $this->categories);

        return sprintf(
            "Classify the following text into exactly ONE of these categories: [%s].\nReturn ONLY the category name without explanation.\n\nText: %s",
            $categoriesList,
            $text
        );
    }

    public function version(): string
    {
        return $this->version;
    }
}
