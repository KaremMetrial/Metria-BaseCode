<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Translation\Exceptions;

class ProviderUnavailableException extends TranslationException
{
    // Thrown when an external provider API is completely unreachable or offline (500/503 errors)
}
