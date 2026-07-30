<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Translation\Exceptions;

class TranslationValidationFailedException extends TranslationException
{
    // Thrown when translated content fails validation checks (e.g. empty strings, bad charset, invalid keys)
}
