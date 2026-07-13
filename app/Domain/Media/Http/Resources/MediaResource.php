<?php

declare(strict_types=1);

namespace App\Domain\Media\Http\Resources;

use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Services\MediaDownloadService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @property Media $resource
 */
class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $downloadService = app(MediaDownloadService::class);

        $downloadUrl = '';
        if ($this->resource->status === MediaStatus::Active && $this->resource->blob !== null) {
            try {
                if ($this->resource->is_public) {
                    $downloadUrl = Storage::disk($this->resource->blob->disk)->url($this->resource->blob->path);
                } else {
                    $downloadUrl = $downloadService->generateDownloadUrl($this->resource);
                }
            } catch (\Throwable) {
                $downloadUrl = '';
            }
        }

        return [
            'id' => $this->resource->id,
            'media_type' => $this->resource->media_type->value,
            'purpose' => $this->resource->purpose,
            'is_public' => $this->resource->is_public,
            'status' => $this->resource->status->value,
            'filename' => $this->resource->custom_properties['filename'] ?? '',
            'size' => $this->resource->blob?->size ?? 0,
            'mime_type' => $this->resource->blob?->mime_type ?? '',
            'download_url' => $downloadUrl,
            'moderation_status' => $this->resource->moderation_status,
            'processing_error' => $this->resource->processing_error,
            'custom_properties' => $this->resource->custom_properties,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'activated_at' => $this->resource->activated_at?->toIso8601String(),
        ];
    }
}
