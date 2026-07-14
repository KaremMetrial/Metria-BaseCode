<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation\Exceptions;

class InvalidTranslationResponseException extends TranslationException
{
    // Thrown when a provider returns a malformed response (e.g. invalid JSON, missing expected keys)
}
