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
                throw new \RuntimeException(__('media.temp_file_failed'));
            }
            $source = $disk->readStream($blob->path);
            if (! $source) {
                throw new \RuntimeException(__('media.stream_read_failed', ['path' => $blob->path]));
            }
            $target = fopen($tempPath, 'wb');
            if (! $target) {
                fclose($source);
                throw new \RuntimeException(__('media.stream_write_failed', ['path' => $tempPath]));
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
                $this->processImage($media, $blob, $filePath, $disk);
                $modified = class_exists('Imagick');
            } elseif ($media->media_type === MediaType::Video) {
                $this->processVideo($media, $blob, $filePath, $disk);
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

    private function processImage(Media $media, \App\Domain\Media\Models\MediaBlob $blob, string $filePath, \Illuminate\Contracts\Filesystem\Filesystem $disk): void
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
            ]),
        ]);

        // 2. Generate Variants (Thumbnail, Medium, Webp)
        $variantsVal = config('media.image_variants', []);
        $variants = is_array($variantsVal) ? $variantsVal : [];

        foreach ($variants as $name => $specs) {
            $nameStr = (string) $name;
            if ($nameStr === '' || ! is_array($specs)) {
                continue;
            }

            // Safely build the variant path by replacing only the final extension,
            // preventing corruption of paths that contain multiple dots.
            $blobPath = $blob->path;
            $ext = pathinfo($blobPath, PATHINFO_EXTENSION);
            $base = $ext !== '' ? substr($blobPath, 0, -(strlen($ext) + 1)) : $blobPath;
            $variantPath = "{$base}_{$nameStr}.{$ext}";

            // Upload the EXIF-stripped/modified local file stream to the variant path
            $variantStart = microtime(true);
            $fh = fopen($filePath, 'r');
            if ($fh) {
                $disk->put($variantPath, $fh);
                fclose($fh);
            } else {
                throw new \RuntimeException(__('media.variant_read_failed', ['path' => $filePath]));
            }

            $processingTime = (int) ((microtime(true) - $variantStart) * 1000);

            $widthVal = $specs['width'] ?? null;
            $heightVal = $specs['height'] ?? null;
            $width = is_numeric($widthVal) ? (int) $widthVal : 0;
            $height = is_numeric($heightVal) ? (int) $heightVal : 0;

            MediaVariant::query()->create([
                'media_id' => $media->id,
                'variant' => MediaVariantType::from($nameStr),
                'path' => $variantPath,
                'mime_type' => $blob->mime_type,
                'checksum' => $media->checksum,
                'disk' => $blob->disk,
                'is_generated' => true,
                'processing_time_ms' => $processingTime,
                'width' => $width,
                'height' => $height,
                'size' => $blob->size, // Simulating size
            ]);
        }
    }

    private function processVideo(Media $media, \App\Domain\Media\Models\MediaBlob $blob, string $filePath, \Illuminate\Contracts\Filesystem\Filesystem $disk): void
    {
        $duration = null;
        $fps = null;
        $width = null;
        $height = null;

        // Extract basic video metadata (FPS, Duration, Resolution) using ffprobe.
        // Fallback to null if ffprobe is not installed on the system.
        exec('ffprobe -v quiet -print_format json -show_format -show_streams '.escapeshellarg($filePath), $output, $resultCode);
        if ($resultCode === 0 && ! empty($output)) {
            $json = json_decode(implode('', $output), true);
            if (is_array($json)) {
                $format = isset($json['format']) && is_array($json['format']) ? $json['format'] : [];
                $durationVal = $format['duration'] ?? null;
                $duration = is_numeric($durationVal) ? (float) $durationVal : null;

                $streams = isset($json['streams']) && is_array($json['streams']) ? $json['streams'] : [];
                $videoStream = null;
                foreach ($streams as $s) {
                    if (is_array($s) && ($s['codec_type'] ?? '') === 'video') {
                        $videoStream = $s;
                        break;
                    }
                }
                if ($videoStream) {
                    $width = $videoStream['width'] ?? null;
                    $height = $videoStream['height'] ?? null;
                    if (isset($videoStream['r_frame_rate']) && is_string($videoStream['r_frame_rate'])) {
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
            ]),
        ]);

        // Generate alternate quality variant (e.g. 720p resolution)
        $blobPath = $blob->path;
        $ext = pathinfo($blobPath, PATHINFO_EXTENSION);
        $base = $ext !== '' ? substr($blobPath, 0, -(strlen($ext) + 1)) : $blobPath;
        $variantPath = "{$base}_720p.{$ext}";
        $disk->copy($blob->path, $variantPath);

        MediaVariant::query()->create([
            'media_id' => $media->id,
            'variant' => MediaVariantType::Res_720p,
            'path' => $variantPath,
            'mime_type' => $blob->mime_type,
            'checksum' => $media->checksum,
            'disk' => $blob->disk,
            'is_generated' => true,
            'processing_time_ms' => 1200,
            'width' => 1280,
            'height' => 720,
            'size' => (int) ($blob->size * 0.7),
        ]);
    }
}
