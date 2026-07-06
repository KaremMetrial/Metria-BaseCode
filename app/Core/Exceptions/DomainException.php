<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

class DomainException extends ApiException
{
    public function __construct(string $message, string $errorCode = 'domain_error', array $context = [])
    {
        parent::__construct($message, status: 422, errorCode: $errorCode, context: $context);
    }
}
