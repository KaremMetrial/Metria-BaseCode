<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Domain\Media\Contracts\VirusScanner;
use App\Domain\Media\DTOs\VirusScanResult;

class ClamAvVirusScanner implements VirusScanner
{
    public function scan(string $filePath): VirusScanResult
    {
        $startTime = microtime(true);

        // Simulate local scanner connection and execution
        usleep(10000); // 10ms delay

        $filename = basename($filePath);
        
        // If file contains 'infected' or eicar signature, simulate malware detection
        if (str_contains(strtolower($filename), 'infected') || str_contains(strtolower($filename), 'eicar')) {
            return new VirusScanResult(
                status: 'infected',
                engine: 'ClamAV',
                version: '1.4.0',
                signatureVersion: '27000',
                duration: microtime(true) - $startTime,
                infectedFiles: [$filename],
                message: 'Win.Test.EICAR_HSTR-1 FOUND',
                rawResponse: ['status' => 'FOUND', 'virus' => 'Win.Test.EICAR_HSTR-1']
            );
        }

        return new VirusScanResult(
            status: 'safe',
            engine: 'ClamAV',
            version: '1.4.0',
            signatureVersion: '27000',
            duration: microtime(true) - $startTime,
            infectedFiles: [],
            message: 'OK',
            rawResponse: ['status' => 'OK']
        );
    }
}
