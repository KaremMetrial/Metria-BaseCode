<?php

declare(strict_types=1);

namespace App\Core\Support;

/**
 * Lightweight Result object so services can return explicit success/failure
 * without throwing exceptions for expected business outcomes.
 */
final readonly class Result
{
    private function __construct(
        public bool $success,
        public mixed $value = null,
        public ?string $error = null,
        public ?string $errorCode = null,
    ) {}

    public static function ok(mixed $value = null): self
    {
        return new self(success: true, value: $value);
    }

    public static function fail(string $error, ?string $errorCode = null): self
    {
        return new self(success: false, error: $error, errorCode: $errorCode);
    }

    public function failed(): bool
    {
        return ! $this->success;
    }

    public function map(callable $fn): self
    {
        return $this->success ? self::ok($fn($this->value)) : $this;
    }

    public function valueOrThrow(?\Throwable $e = null): mixed
    {
        if ($this->failed()) {
            throw $e ?? new \RuntimeException($this->error ?? 'Operation failed');
        }

        return $this->value;
    }
}
