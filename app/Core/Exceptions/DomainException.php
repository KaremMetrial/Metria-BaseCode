<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

class DomainException extends ApiException
{
    public function __construct(string $message, string $errorCode = 'domain_error', array $context = [], ?\Throwable $previous = null, int $status = 422)
    {
        parent::__construct($message, status: $status, errorCode: $errorCode, context: $context, previous: $previous);
    }
}
