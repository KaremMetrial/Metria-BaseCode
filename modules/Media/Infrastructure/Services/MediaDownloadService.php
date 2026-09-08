<?php

declare(strict_types=1);

namespace Modules\Media\Infrastructure\Services;

use Modules\Media\Domain\Models\Media;
use Illuminate\Support\Facades\Storage;

class MediaDownloadService
{
    /**
     * Build the (possibly temporary/CDN-mapped) download URL only — no
     * side effects. Safe to call from read paths like resource
     * serialization, which must never mutate state as a side effect of a
     * GET request.
     */
    public function generateUrl(Media $media, int $expiresInSeconds = 3600): string
    {
        $blob = $media->blob;
        if (! $blob) {
            throw new \RuntimeException(__('media.missing_blob'));
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
        $cdnUrlVal = config('media.cdn_url');
        $cdnUrl = is_string($cdnUrlVal) ? $cdnUrlVal : '';
        if ($cdnUrl !== '') {
            $url = str_replace(url('/'), rtrim($cdnUrl, '/'), $url);
        }

        return $url;
    }

    /**
     * Same as generateUrl(), but records the download — use only from an
     * actual download action, never from serialization/listing, or every
     * GET that touches this resource silently inflates the counter.
     */
    public function generateDownloadUrl(Media $media, int $expiresInSeconds = 3600): string
    {
        $url = $this->generateUrl($media, $expiresInSeconds);

        // Atomic UPDATE ... SET download_count = download_count + 1, so
        // concurrent downloads of the same media don't lose updates to a
        // stale in-memory read of the counter.
        $media->increment('download_count');
        $media->update(['last_downloaded_at' => now()]);

        return $url;
    }
}
