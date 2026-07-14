<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation\Exceptions;

use Carbon\Carbon;

class RateLimitedException extends TranslationException
{
    private ?Carbon $retryAfter = null;

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, ?Carbon $retryAfter = null)
    {
        parent::__construct($message, $code, $previous);
        $this->retryAfter = $retryAfter;
    }

    public function getRetryAfter(): ?Carbon
    {
        return $this->retryAfter;
    }
}
