<?php

declare(strict_types=1);

namespace App\Domain\Media\DTOs;

final class ModerationResult
{
    public function __construct(
        public readonly bool $approved,
        public readonly string $provider,
        public readonly float $confidence = 100.0,
        public readonly array $labels = [],
        public readonly float $adultScore = 0.0,
        public readonly float $violenceScore = 0.0,
        public readonly float $selfHarmScore = 0.0,
        public readonly float $hateSpeechScore = 0.0,
        public readonly ?array $rawResponse = null
    ) {}
}
