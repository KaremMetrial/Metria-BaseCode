<?php

declare(strict_types=1);

namespace App\Infrastructure\Translation\Exceptions;

class UnsupportedLocalePairException extends TranslationException
{
    // Thrown when a provider does not support translating between the requested locales
}
