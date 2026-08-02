<?php

declare(strict_types=1);

namespace Modules\Media\Domain\Contracts;

use Modules\Media\Domain\DTOs\VirusScanResult;

interface VirusScanner
{
    public function scan(string $filePath): VirusScanResult;
}
