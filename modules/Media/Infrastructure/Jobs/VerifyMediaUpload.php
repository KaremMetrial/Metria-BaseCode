<?php

declare(strict_types=1);

namespace Modules\Media\Infrastructure\Jobs;

use Modules\Media\Domain\Enums\MediaStatus;
use Modules\Media\Domain\Models\Media;
use Modules\Media\Infrastructure\Services\MediaVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VerifyMediaUpload implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(2);
    }

    public function __construct(private readonly string $mediaId) {}

    public function handle(MediaVerificationService $verifier): void
    {
        /** @var Media|null $media */
        $media = Media::query()->find($this->mediaId);

        if (! $media) {
            Log::warning("Media record [{$this->mediaId}] not found for verification. Aborting.");

            return;
        }

        try {
            Log::info("Starting verification pipeline for media [{$this->mediaId}].");
            $verifier->verify($media);
        } catch (\Throwable $e) {
            Log::error("Failed verification for media [{$this->mediaId}]: ".$e->getMessage());

            $media->increment('retry_count');

            if ($media->retry_count >= $this->tries) {
                $media->update([
                    'status' => MediaStatus::Failed,
                    'processing_error' => 'Verification failed after max retries: '.$e->getMessage(),
                ]);
            }

            throw $e;
        }
    }
}
