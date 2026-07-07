<?php

declare(strict_types=1);

namespace App\Domain\Media\Contracts;

use App\Domain\Media\DTOs\ModerationResult;

interface ContentModerator
{
    public function moderate(string $filePath): ModerationResult;
}
