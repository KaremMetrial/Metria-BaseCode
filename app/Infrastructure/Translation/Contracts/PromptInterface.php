<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation\Contracts;

interface PromptInterface
{
    /**
     * Render the prompt text or instructions given an array of context values.
     *
     * @param array<string, mixed> $context
     */
    public function render(array $context): string;

    /**
     * Get the version string of the prompt (e.g. 'v1.2').
     * Changing the version MUST automatically invalidate cached responses.
     */
    public function version(): string;
}
