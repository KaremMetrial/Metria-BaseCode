<?php

declare(strict_types=1);

namespace App\Domain\Media\Jobs;

use App\Domain\Media\Models\Media;
use App\Domain\Media\Services\MediaProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMediaVariants implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(private readonly string $mediaId) {}

    public function handle(MediaProcessingService $processor): void
    {
        /** @var Media $media */
        $media = Media::query()->find($this->mediaId);

        if (! $media) {
            Log::warning("Media record [{$this->mediaId}] not found for processing. Aborting.");
            return;
        }

        try {
            Log::info("Starting optimization/variant pipeline for media [{$this->mediaId}].");
            $processor->process($media);
        } catch (\Throwable $e) {
            Log::error("Failed variant generation for media [{$this->mediaId}]: " . $e->getMessage());

            $media->increment('retry_count');

            if ($media->retry_count >= $this->tries) {
                $media->update([
                    'status' => \App\Domain\Media\Enums\MediaStatus::Failed,
                    'processing_error' => 'Processing failed after max retries: ' . $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }
}
