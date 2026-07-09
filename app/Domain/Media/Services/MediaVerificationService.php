<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Domain\Media\Contracts\ContentModerator;
use App\Domain\Media\Contracts\VirusScanner;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Events\MediaVerified;
use App\Domain\Media\Events\MediaQuarantined;
use App\Domain\Media\Jobs\ProcessMediaVariants;
use App\Domain\Media\Models\Media;
use Illuminate\Support\Facades\Storage;

class MediaVerificationService
{
    public function __construct(
        private readonly VirusScanner $virusScanner,
        private readonly ContentModerator $moderator,
        private readonly MediaStateMachine $stateMachine
    ) {}

    public function verify(Media $media): void
    {
        $blob = $media->blob;
        if (! $blob) {
            $this->stateMachine->transition($media, MediaStatus::Failed);
            return;
        }

        $disk = Storage::disk($blob->disk);
        
        $isLocal = false;
        try {
            $filePath = $disk->path($blob->path);
            $isLocal = true;
        } catch (\Throwable) {
            $tempPath = tempnam(sys_get_temp_dir(), 'media_verify_');
            if ($tempPath === false) {
                throw new \RuntimeException("Failed to create temporary file.");
            }
            $source = $disk->readStream($blob->path);
            if (! $source) {
                throw new \RuntimeException("Failed to open read stream for path: {$blob->path}");
            }
            $target = fopen($tempPath, 'wb');
            if (! $target) {
                fclose($source);
                throw new \RuntimeException("Failed to open write stream for path: {$tempPath}");
            }
            stream_copy_to_stream($source, $target);
            fclose($source);
            fclose($target);
            $filePath = $tempPath;
        }

        try {
            // 1. Virus Scanning
            if (config('media.virus_scan_enabled', true)) {
                $scanResult = $this->virusScanner->scan($filePath);
                
                $blob->virus_status = $scanResult->status;
                $blob->virus_scan_details = [
                    'engine' => $scanResult->engine,
                    'version' => $scanResult->version,
                    'message' => $scanResult->message,
                    'infected_files' => $scanResult->infectedFiles,
                    'duration' => $scanResult->duration,
                ];
                $blob->save();

                if (! $scanResult->isClean()) {
                    $this->stateMachine->transition($media, MediaStatus::Quarantined);
                    $media->update([
                        'processing_error' => __('media.virus_detected'),
                        'quarantined_at' => now(),
                    ]);
                    event(new MediaQuarantined($media, 'virus_detected'));
                    return;
                }
            }

            // 2. Content Moderation
            if (config('media.moderation_enabled', true) && in_array($media->media_type, [MediaType::Image, MediaType::Video], true)) {
                $modResult = $this->moderator->moderate($filePath);
                
                $media->moderation_status = $modResult->approved ? 'approved' : 'flagged';
                $media->moderation_details = [
                    'provider' => $modResult->provider,
                    'confidence' => $modResult->confidence,
                    'labels' => $modResult->labels,
                    'adultScore' => $modResult->adultScore,
                    'violenceScore' => $modResult->violenceScore,
                ];
                $media->save();

                if (! $modResult->approved) {
                    $this->stateMachine->transition($media, MediaStatus::Quarantined);
                    $media->update([
                        'processing_error' => __('media.nsfw_detected'),
                        'quarantined_at' => now(),
                    ]);
                    event(new MediaQuarantined($media, 'nsfw_detected'));
                    return;
                }
            }

            // Mark blob as verified
            $blob->update(['verified_at' => now()]);

            // Transition to processing state
            $this->stateMachine->transition($media, MediaStatus::Processing);

            event(new MediaVerified($media));

            // Trigger asynchronous variant/optimization pipeline job
            ProcessMediaVariants::dispatch($media->id);
        } finally {
            if (!$isLocal && file_exists($filePath)) {
                @unlink($filePath);
            }
        }
    }
}
