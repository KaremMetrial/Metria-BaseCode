<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Core\Exceptions\DomainException;
use App\Domain\Auth\Models\User;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Enums\MediaType;
use App\Domain\Media\Events\MediaUploaded;
use App\Domain\Media\Events\MediaUploadInitiated;
use App\Domain\Media\Jobs\VerifyMediaUpload;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaBlob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUploadService
{
    public function __construct(
        private readonly MediaStateMachine $stateMachine
    ) {}

    public function initiateUpload(
        User $user,
        string $filename,
        string $mimeType,
        int $size,
        bool $isPublic = false,
        string $purpose = 'attachment',
        array $options = []
    ): array {
        $maxSize = config('media.max_file_size_bytes', 500 * 1024 * 1024);
        if ($size > $maxSize) {
            throw new DomainException(__('media.file_too_large'), errorCode: 'file_too_large');
        }

        $allowedMimes = config('media.allowed_mimes', []);
        if (! in_array($mimeType, $allowedMimes, true)) {
            throw new DomainException(__('media.disallowed_mime_type', ['mime' => $mimeType]), errorCode: 'disallowed_mime_type');
        }

        // Determine media category type
        $mediaType = $this->determineMediaType($mimeType);

        // Enforce tenant boundary scoping
        $tenantId = $user->tenant_id;
        $mediaId = (string) Str::uuid();

        // Sanitize filename to prevent directory traversal
        $sanitizedName = (string) preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($filename));
        $extension = pathinfo($sanitizedName, PATHINFO_EXTENSION);
        $diskName = $isPublic ? config('media.default_disk', 'public') : config('media.private_disk', 'local');

        // Storage path layout: tenants/{tenant_id}/{media_type}/{uuid}.{ext}
        $storagePath = sprintf('tenants/%s/%s/%s.%s', $tenantId ?? 'global', $mediaType->value, $mediaId, $extension ?: 'bin');

        return DB::transaction(function () use ($user, $tenantId, $mediaId, $mediaType, $purpose, $isPublic, $sanitizedName, $size, $diskName, $storagePath, $options): array {
            $media = Media::query()->create([
                'id' => $mediaId,
                'tenant_id' => $tenantId,
                'media_type' => $mediaType,
                'purpose' => $purpose,
                'is_public' => $isPublic,
                'status' => MediaStatus::Pending,
                'custom_properties' => array_merge($options, [
                    'filename' => $sanitizedName,
                    'disk' => $diskName,
                    'path' => $storagePath,
                ]),
                'created_by' => $user->id,
            ]);

            // Generate direct upload presigned URL (S3/R2 mock simulation or real driver url)
            $disk = Storage::disk($diskName);

            // Check if disk adapter supports temporary upload URLs (like AWS S3), otherwise fallback to local upload url
            $presignedUrl = '';
            try {
                if (method_exists($disk->getAdapter(), 'temporaryUploadUrl')) {
                    $urlResult = $disk->temporaryUploadUrl($storagePath, now()->addMinutes(60));
                    $presignedUrl = is_array($urlResult) ? (string) reset($urlResult) : (string) $urlResult;
                } else {
                    // Fallback to local API upload endpoint
                    $presignedUrl = (string) route('media.confirm', ['media' => $mediaId]);
                }
            } catch (\Throwable) {
                $presignedUrl = (string) route('media.confirm', ['media' => $mediaId]);
            }

            $presignedUrlStr = (string) $presignedUrl;

            // If file is larger than 100MB, return multipart upload parameters
            $multipart = [];
            if ($size > 100 * 1024 * 1024) {
                $multipart = [
                    'upload_id' => (string) Str::uuid(),
                    'chunk_size' => 10 * 1024 * 1024, // 10MB chunks
                    'urls' => [
                        $presignedUrlStr.'?part=1',
                        $presignedUrlStr.'?part=2',
                    ],
                ];
            }

            event(new MediaUploadInitiated($media));

            return [
                'media_id' => $mediaId,
                'upload_url' => $presignedUrl,
                'multipart' => $multipart,
                'path' => $storagePath,
            ];
        });
    }

    public function confirmUpload(string $mediaId, string $clientChecksum, ?string $idempotencyKey = null): Media
    {
        try {
            return DB::transaction(function () use ($mediaId, $clientChecksum): Media {
                // Pessimistic locking to prevent double confirmation race conditions
                /** @var Media $media */
                $media = Media::query()->lockForUpdate()->findOrFail($mediaId);

                // If already confirmed or processing, return early (idempotent)
                if ($media->status !== MediaStatus::Pending && $media->status !== MediaStatus::Uploading) {
                    return $media;
                }

                $diskName = $media->custom_properties['disk'];
                $storagePath = $media->custom_properties['path'];
                $filename = $media->custom_properties['filename'];
                $disk = Storage::disk($diskName);

                // Ensure physical file exists in storage
                if (! $disk->exists($storagePath)) {
                    throw new DomainException(__('media.file_not_found'), errorCode: 'file_not_found');
                }

                // Verify actual file size
                $actualSize = $disk->size($storagePath);

                // Server-side compute checksum to prevent spoofing and support cloud/S3
                $stream = $disk->readStream($storagePath);
                if (! $stream) {
                    throw new DomainException(__('media.file_not_found'), errorCode: 'file_not_found');
                }
                $ctx = hash_init('sha256');
                hash_update_stream($ctx, $stream);
                fclose($stream);
                $actualHash = hash_final($ctx);

                if ($actualHash !== $clientChecksum) {
                    throw new DomainException(__('media.checksum_mismatch'), errorCode: 'checksum_mismatch');
                }

                $actualMime = $disk->mimeType($storagePath) ?: 'application/octet-stream';

                // Check if tenant-scoped deduplication is possible
                $tenantId = $media->tenant_id;

                /** @var MediaBlob|null $existingBlob */
                $existingBlob = MediaBlob::query()
                    ->where('tenant_id', $tenantId)
                    ->where('sha256', $actualHash)
                    ->where('virus_status', 'safe') // Only link to clean blobs
                    ->first();

                if ($existingBlob) {
                    // Link logical Media to the existing clean Blob
                    $media->media_blob_id = $existingBlob->id;
                    $media->save();

                    // Deduplication: Delete redundant physical file since we already have it!
                    $disk->delete($storagePath);
                } else {
                    // Create a new physical Blob entry
                    $blob = MediaBlob::query()->create([
                        'tenant_id' => $tenantId,
                        'sha256' => $actualHash,
                        'disk' => $diskName,
                        'path' => $storagePath,
                        'filename' => $filename,
                        'original_filename' => $filename,
                        'mime_type' => $actualMime,
                        'size' => $actualSize,
                        'virus_status' => 'pending',
                        'uploaded_at' => now(),
                    ]);

                    $media->media_blob_id = $blob->id;
                    $media->save();
                }

                // Set checksum on Logical media
                $media->checksum = $actualHash;
                $media->hash_algorithm = 'sha256';
                $media->save();

                // Transition status to uploaded
                $this->stateMachine->transition($media, MediaStatus::Uploaded);

                // Transition to verifying and queue asynchronous scan pipeline
                $this->stateMachine->transition($media, MediaStatus::Verifying);

                event(new MediaUploaded($media));

                VerifyMediaUpload::dispatch($media->id);

                return $media->refresh();
            });
        } catch (DomainException $e) {
            if ($e->errorCode === 'checksum_mismatch') {
                $media = Media::query()->findOrFail($mediaId);
                $this->stateMachine->transition($media, MediaStatus::Failed);
                $media->update(['processing_error' => __('media.checksum_failed')]);
            }
            throw $e;
        }
    }

    public function storeUploadedFile(
        UploadedFile $file,
        ?User $user,
        ?string $tenantId = null,
        string $purpose = 'attachment',
        bool $isPublic = false,
        array $options = [],
        ?string $mediableType = null,
        ?string $mediableId = null
    ): Media {
        $maxSize = config('media.max_file_size_bytes', 500 * 1024 * 1024);
        if ($file->getSize() > $maxSize) {
            throw new DomainException(__('media.file_too_large'), errorCode: 'file_too_large');
        }

        $allowedMimes = config('media.allowed_mimes', []);
        $mimeType = (string) $file->getMimeType();
        if (! in_array($mimeType, $allowedMimes, true)) {
            throw new DomainException(__('media.disallowed_mime_type', ['mime' => $mimeType]), errorCode: 'disallowed_mime_type');
        }

        $mediaType = $this->determineMediaType($mimeType);
        $mediaId = (string) Str::uuid();

        $sanitizedName = (string) preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($file->getClientOriginalName()));
        $extension = pathinfo($sanitizedName, PATHINFO_EXTENSION);
        $diskName = $isPublic ? config('media.default_disk', 'public') : config('media.private_disk', 'local');
        $storagePath = sprintf('tenants/%s/%s/%s.%s', $tenantId ?? 'global', $mediaType->value, $mediaId, $extension ?: 'bin');

        $disk = Storage::disk($diskName);
        $disk->putFileAs(dirname($storagePath), $file, basename($storagePath));

        $checksum = (string) hash_file('sha256', (string) $file->getRealPath());

        return DB::transaction(function () use ($user, $tenantId, $mediaId, $mediaType, $purpose, $isPublic, $sanitizedName, $mimeType, $checksum, $diskName, $storagePath, $options, $mediableType, $mediableId): Media {
            $blob = MediaBlob::query()->create([
                'tenant_id' => $tenantId,
                'sha256' => $checksum,
                'disk' => $diskName,
                'path' => $storagePath,
                'filename' => $sanitizedName,
                'original_filename' => $sanitizedName,
                'mime_type' => $mimeType,
                'size' => Storage::disk($diskName)->size($storagePath),
                'virus_status' => 'pending',
                'uploaded_at' => now(),
            ]);

            $media = Media::query()->create([
                'id' => $mediaId,
                'tenant_id' => $tenantId,
                'media_blob_id' => $blob->id,
                'mediable_type' => $mediableType,
                'mediable_id' => $mediableId,
                'media_type' => $mediaType,
                'purpose' => $purpose,
                'is_public' => $isPublic,
                'status' => MediaStatus::Verifying,
                'checksum' => $checksum,
                'hash_algorithm' => 'sha256',
                'custom_properties' => array_merge($options, [
                    'filename' => $sanitizedName,
                    'disk' => $diskName,
                    'path' => $storagePath,
                ]),
                'created_by' => $user?->id,
            ]);

            event(new MediaUploaded($media));

            VerifyMediaUpload::dispatch($media->id);

            return $media;
        });
    }

    private function determineMediaType(string $mimeType): MediaType
    {
        if (str_starts_with($mimeType, 'image/')) {
            return MediaType::Image;
        }
        if (str_starts_with($mimeType, 'video/')) {
            return MediaType::Video;
        }
        if (str_starts_with($mimeType, 'audio/')) {
            return MediaType::Audio;
        }
        if (in_array($mimeType, ['application/pdf', 'application/msword', 'text/plain'], true)) {
            return MediaType::Document;
        }

        return MediaType::Archive;
    }
}
