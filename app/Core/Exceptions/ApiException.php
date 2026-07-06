<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Exception;

/**
 * Base exception for anything that should surface to API consumers with a
 * stable machine-readable error code. Rendered centrally in bootstrap/app.php.
 */
class ApiException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $status = 400,
        public readonly string $errorCode = 'error',
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
