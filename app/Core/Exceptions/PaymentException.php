<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

class PaymentException extends ApiException
{
    public function __construct(string $message, string $errorCode = 'payment_error', array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, status: 402, errorCode: $errorCode, context: $context, previous: $previous);
    }
}
