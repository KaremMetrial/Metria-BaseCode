<?php

declare(strict_types=1);

namespace Modules\Communication\Infrastructure\Support;

use InvalidArgumentException;
use Modules\Communication\Domain\Enums\MessageKind;

/**
 * Strict, phase-scoped schema registry. New kinds are added with an explicit
 * validator and contract test rather than accepted as arbitrary JSON.
 */
final class MessageKindRegistry
{
    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function validate(string $kind, array $content, bool $systemActor = false): array
    {
        $messageKind = MessageKind::tryFrom($kind);

        if ($messageKind === null) {
            throw new InvalidArgumentException('COMMUNICATION_MESSAGE_KIND_INVALID');
        }

        return match ($messageKind) {
            MessageKind::Text => $this->text($content, 'text'),
            MessageKind::Markdown => $this->text($content, 'markdown'),
            MessageKind::System => $systemActor ? $this->text($content, 'text') : throw new InvalidArgumentException('COMMUNICATION_MESSAGE_KIND_INVALID'),
        };
    }

    /** @param array<string, mixed> $content
     * @return array<string, mixed>
     */
    private function text(array $content, string $key): array
    {
        if (array_keys($content) !== [$key] || ! is_string($content[$key])) {
            throw new InvalidArgumentException('COMMUNICATION_MESSAGE_KIND_INVALID');
        }

        $value = trim($content[$key]);
        if ($value === '' || mb_strlen($value) > 10_000) {
            throw new InvalidArgumentException('COMMUNICATION_MESSAGE_KIND_INVALID');
        }

        return [$key => $value];
    }
}
