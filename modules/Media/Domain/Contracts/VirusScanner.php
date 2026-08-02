<?php

declare(strict_types=1);

namespace App\Domain\Media\Contracts;

use App\Domain\Media\DTOs\VirusScanResult;

interface VirusScanner
{
    public function scan(string $filePath): VirusScanResult;
}
