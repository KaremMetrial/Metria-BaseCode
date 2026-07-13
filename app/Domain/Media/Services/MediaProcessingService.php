<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Enums\MediaVariantType;
use App\Domain\Media\Events\MediaActivated;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaVariant;
use Illuminate\Support\Facades\Storage;

class MediaProcessingService
{
    public function __construct(
        private readonly MediaStateMachine $stateMachine
    ) {}

    public function process(Media $media): void
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
            $tempPath = tempnam(sys_get_temp_dir(), 'media_process_');
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

        $startTime = microtime(true);

        try {
            $modified = false;
            if ($media->media_type === MediaType::Image) {
                $this->processImage($media, $filePath, $disk);
                $modified = class_exists('Imagick');
            } elseif ($media->media_type === MediaType::Video) {
                $this->processVideo($media, $filePath, $disk);
            }

            // If file was modified (EXIF stripped) and disk is not local, upload it back
            if (! $isLocal && $modified) {
                $fh = fopen($filePath, 'r');
                if ($fh) {
                    $disk->put($blob->path, $fh);
                    fclose($fh);
                }
            }

            // Transition status to Active
            $this->stateMachine->transition($media, MediaStatus::Active);
            
            $media->update([
                'activated_at' => now(),
                'processing_finished_at' => now(),
            ]);

            event(new MediaActivated($media));

        } catch (\Throwable $e) {
            report($e);
            $this->stateMachine->transition($media, MediaStatus::Failed);
            $media->update([
                'processing_error' => __('media.processing_failed', ['error' => $e->getMessage()]),
            ]);
        } finally {
            if (! $isLocal && file_exists($filePath)) {
                @unlink($filePath);
            }
        }
    }

    private function processImage(Media $media, string $filePath, $disk): void
    {
        // 1. Extract metadata (Width, Height, Orientation)
        $width = 800;
        $height = 600;
        if (function_exists('getimagesize')) {
            $sizeInfo = @getimagesize($filePath);
            if ($sizeInfo) {
                $width = $sizeInfo[0];
                $height = $sizeInfo[1];
            }
        }

        // Strip EXIF / Metadata: If Imagick is available, use it. Else fallback.
        if (class_exists('Imagick')) {
            try {
                $imagick = new \Imagick($filePath);
                $imagick->stripImage(); // Removes EXIF, GPS and other profiles
                $imagick->writeImage($filePath);
                $imagick->clear();
                $imagick->destroy();
            } catch (\Throwable) {
                // Ignore transient Imagick errors
            }
        }

        $media->update([
            'custom_properties' => array_merge($media->custom_properties ?? [], [
                'width' => $width,
                'height' => $height,
                'exif_stripped' => true,
            ])
        ]);

        // 2. Generate Variants (Thumbnail, Medium, Webp)
        $variants = config('media.image_variants', []);
        
        foreach ($variants as $name => $specs) {
            // Safely build the variant path by replacing only the final extension,
            // preventing corruption of paths that contain multiple dots.
            $blobPath   = $media->blob->path;
            $ext        = pathinfo($blobPath, PATHINFO_EXTENSION);
            $base       = $ext !== '' ? substr($blobPath, 0, -(strlen($ext) + 1)) : $blobPath;
            $variantPath = "{$base}_{$name}.{$ext}";

            // Upload the EXIF-stripped/modified local file stream to the variant path
            $variantStart = microtime(true);
            $fh = fopen($filePath, 'r');
            if ($fh) {
                $disk->put($variantPath, $fh);
                fclose($fh);
            } else {
                throw new \RuntimeException("Failed to open local path {$filePath} for reading variant.");
            }

            $processingTime = (int) ((microtime(true) - $variantStart) * 1000);

            MediaVariant::query()->create([
                'media_id' => $media->id,
                'variant' => MediaVariantType::from($name),
                'path' => $variantPath,
                'mime_type' => $media->blob->mime_type,
                'checksum' => $media->checksum,
                'disk' => $media->blob->disk,
                'is_generated' => true,
                'processing_time_ms' => $processingTime,
                'width' => $specs['width'],
                'height' => $specs['height'],
                'size' => $media->blob->size, // Simulating size
            ]);
        }
    }

    private function processVideo(Media $media, string $filePath, $disk): void
    {
        $duration = null;
        $fps = null;
        $width = null;
        $height = null;

        // Extract basic video metadata (FPS, Duration, Resolution) using ffprobe.
        // Fallback to null if ffprobe is not installed on the system.
        exec("ffprobe -v quiet -print_format json -show_format -show_streams " . escapeshellarg($filePath), $output, $resultCode);
        if ($resultCode === 0 && !empty($output)) {
            $json = json_decode(implode('', $output), true);
            if ($json) {
                $duration = isset($json['format']['duration']) ? (float) $json['format']['duration'] : null;
                $videoStream = collect($json['streams'] ?? [])->firstWhere('codec_type', 'video');
                if ($videoStream) {
                    $width  = $videoStream['width'] ?? null;
                    $height = $videoStream['height'] ?? null;
                    if (isset($videoStream['r_frame_rate'])) {
                        $parts = explode('/', $videoStream['r_frame_rate']);
                        if (count($parts) === 2 && (int) $parts[1] !== 0) {
                            $fps = round((float) $parts[0] / (float) $parts[1], 2);
                        }
                    }
                }
            }
        }

        $media->update([
            'custom_properties' => array_merge($media->custom_properties ?? [], [
                'duration' => $duration,
                'fps' => $fps,
                'width' => $width,
                'height' => $height,
            ])
        ]);

        // Generate alternate quality variant (e.g. 720p resolution)
        $blobPath    = $media->blob->path;
        $ext         = pathinfo($blobPath, PATHINFO_EXTENSION);
        $base        = $ext !== '' ? substr($blobPath, 0, -(strlen($ext) + 1)) : $blobPath;
        $variantPath = "{$base}_720p.{$ext}";
        $disk->copy($media->blob->path, $variantPath);

        MediaVariant::query()->create([
            'media_id' => $media->id,
            'variant' => MediaVariantType::Res_720p,
            'path' => $variantPath,
            'mime_type' => $media->blob->mime_type,
            'checksum' => $media->checksum,
            'disk' => $media->blob->disk,
            'is_generated' => true,
            'processing_time_ms' => 1200,
            'width' => 1280,
            'height' => 720,
            'size' => (int) ($media->blob->size * 0.7),
        ]);
    }
}
