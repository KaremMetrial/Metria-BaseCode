<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Domain\Media\Models\Media;
use Illuminate\Support\Facades\Storage;

class MediaDownloadService
{
    public function generateDownloadUrl(Media $media, int $expiresInSeconds = 3600): string
    {
        $blob = $media->blob;
        if (! $blob) {
            throw new \RuntimeException("No file blob is attached to this media.");
        }

        $disk = Storage::disk($blob->disk);

        // Generate temporary URL if supported by storage disk (e.g. S3), otherwise default to route generator
        $url = '';
        try {
            if (method_exists($disk, 'temporaryUrl')) {
                $url = $disk->temporaryUrl($blob->path, now()->addSeconds($expiresInSeconds));
            } else {
                $url = Storage::url($blob->path);
            }
        } catch (\Throwable) {
            $url = Storage::url($blob->path);
        }

        // Apply CDN mapping if configured
        $cdnUrl = config('media.cdn_url');
        if ($cdnUrl) {
            $url = str_replace(url('/'), rtrim($cdnUrl, '/'), $url);
        }

        // Increment download count and update timing details (auditing)
        $media->increment('download_count');
        $media->update([
            'last_downloaded_at' => now(),
        ]);

        return $url;
    }
}
