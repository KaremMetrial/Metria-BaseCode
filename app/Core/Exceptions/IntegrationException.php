<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

class IntegrationException extends ApiException
{
    public function __construct(string $message, public readonly string $provider = 'unknown', array $context = [])
    {
        parent::__construct($message, status: 502, errorCode: 'integration_error', context: $context + ['provider' => $provider]);
    }
}
