<?php

declare(strict_types=1);

namespace Modules\Media\Domain\Contracts;

use Modules\Media\Domain\DTOs\ModerationResult;

interface ContentModerator
{
    public function moderate(string $filePath): ModerationResult;
}
