<?php

declare(strict_types=1);

namespace App\Domain\Media\DTOs;

final class VirusScanResult
{
    public function __construct(
        public readonly string $status, // 'safe' or 'infected' or 'failed'
        public readonly string $engine,
        public readonly ?string $version = null,
        public readonly ?string $signatureVersion = null,
        public readonly float $duration = 0.0,
        public readonly array $infectedFiles = [],
        public readonly ?string $message = null,
        public readonly ?array $rawResponse = null
    ) {}

    public function isClean(): bool
    {
        return $this->status === 'safe';
    }
}
