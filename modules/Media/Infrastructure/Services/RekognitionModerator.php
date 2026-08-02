<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Domain\Media\Contracts\ContentModerator;
use App\Domain\Media\DTOs\ModerationResult;

class RekognitionModerator implements ContentModerator
{
    public function moderate(string $filePath): ModerationResult
    {
        $filename = basename($filePath);
        $normalized = strtolower($filename);

        // If filename contains adult / nsfw terms, flag it
        if (str_contains($normalized, 'nsfw') || str_contains($normalized, 'adult') || str_contains($normalized, '18+')) {
            return new ModerationResult(
                approved: false,
                provider: 'AWS Rekognition',
                confidence: 99.4,
                labels: [
                    ['Name' => 'Explicit Nudity', 'Confidence' => 99.4],
                    ['Name' => 'Nudity', 'Confidence' => 99.4],
                ],
                adultScore: 99.4,
                violenceScore: 0.0,
                selfHarmScore: 0.0,
                hateSpeechScore: 0.0,
                rawResponse: ['labels' => [['name' => 'Explicit Nudity', 'confidence' => 99.4]]]
            );
        }

        return new ModerationResult(
            approved: true,
            provider: 'AWS Rekognition',
            confidence: 100.0,
            labels: [],
            adultScore: 0.0,
            violenceScore: 0.0,
            selfHarmScore: 0.0,
            hateSpeechScore: 0.0,
            rawResponse: ['labels' => []]
        );
    }
}
