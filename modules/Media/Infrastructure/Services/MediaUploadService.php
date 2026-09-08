<?php

declare(strict_types=1);

namespace Modules\Media\Infrastructure\Services;

use Modules\Shared\Application\Exceptions\DomainException;
use Modules\Auth\Domain\Models\User;
use Modules\Media\Domain\Enums\MediaStatus;
use Modules\Media\Domain\Enums\MediaType;
use Modules\Media\Domain\Events\MediaUploaded;
use Modules\Media\Domain\Events\MediaUploadInitiated;
use Modules\Media\Infrastructure\Jobs\VerifyMediaUpload;
use Modules\Media\Domain\Models\Media;
use Modules\Media\Domain\Models\MediaBlob;
use Illuminate\Database\QueryException;
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
        $maxSizeVal = config('media.max_file_size_bytes', 500 * 1024 * 1024);
        $maxSize = is_numeric($maxSizeVal) ? (int) $maxSizeVal : 500 * 1024 * 1024;
        if ($size > $maxSize) {
            throw new DomainException(__('media.file_too_large'), errorCode: 'file_too_large');
        }

        $allowedMimesConfig = config('media.allowed_mimes', []);
        $allowedMimes = is_array($allowedMimesConfig) ? $allowedMimesConfig : [];
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

        $defaultDiskVal = config('media.default_disk', 'public');
        $privateDiskVal = config('media.private_disk', 'local');
        $defaultDisk = is_string($defaultDiskVal) ? $defaultDiskVal : 'public';
        $privateDisk = is_string($privateDiskVal) ? $privateDiskVal : 'local';
        $diskName = $isPublic ? $defaultDisk : $privateDisk;

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
                    if (is_array($urlResult)) {
                        $first = reset($urlResult);
                        $presignedUrl = is_scalar($first) ? (string) $first : '';
                    } else {
                        $presignedUrl = is_scalar($urlResult) ? (string) $urlResult : '';
                    }
                } else {
                    // Fallback for disks without a real presigned-PUT
                    // adapter (local, public, testing): the client PUTs the
                    // raw file body here, then calls confirm separately.
                    $presignedUrl = (string) route('media.upload', ['media' => $mediaId]);
                }
            } catch (\Throwable) {
                $presignedUrl = (string) route('media.upload', ['media' => $mediaId]);
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

    /**
     * Receives the raw file bytes for the local-disk fallback path:
     * initiateUpload() hands out the `media.upload` route as `upload_url`
     * whenever the disk adapter doesn't support temporaryUploadUrl() (any
     * disk other than a real S3-compatible one — i.e. local/public/testing
     * setups), but there was previously no endpoint that actually accepted
     * a PUT body and wrote it to storage: confirmUpload() would always see
     * a missing file and fail with file_not_found. This is that endpoint's
     * service-side handler — called from a dedicated upload route before
     * the client calls confirm.
     */
    public function receiveLocalUpload(Media $media, string $rawBody): void
    {
        if (! in_array($media->status, [MediaStatus::Pending, MediaStatus::Uploading], true)) {
            throw new DomainException(__('media.already_confirmed'), errorCode: 'already_confirmed');
        }

        $maxSizeVal = config('media.max_file_size_bytes', 500 * 1024 * 1024);
        $maxSize = is_numeric($maxSizeVal) ? (int) $maxSizeVal : 500 * 1024 * 1024;
        if (strlen($rawBody) > $maxSize) {
            throw new DomainException(__('media.file_too_large'), errorCode: 'file_too_large');
        }

        $diskNameVal = $media->custom_properties['disk'] ?? null;
        $diskName = is_string($diskNameVal) ? $diskNameVal : 'public';
        $storagePathVal = $media->custom_properties['path'] ?? null;
        $storagePath = is_string($storagePathVal) ? $storagePathVal : '';

        if ($storagePath === '') {
            throw new DomainException(__('media.file_not_found'), errorCode: 'file_not_found');
        }

        Storage::disk($diskName)->put($storagePath, $rawBody);

        if ($media->status !== MediaStatus::Uploading) {
            $this->stateMachine->transition($media, MediaStatus::Uploading);
        }
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

                $diskNameVal = $media->custom_properties['disk'] ?? null;
                $diskName = is_string($diskNameVal) ? $diskNameVal : 'public';
                $storagePathVal = $media->custom_properties['path'] ?? null;
                $storagePath = is_string($storagePathVal) ? $storagePathVal : '';
                $filenameVal = $media->custom_properties['filename'] ?? null;
                $filename = is_string($filenameVal) ? $filenameVal : '';
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
                    // Skipping virus scanning is an explicit operator choice
                    // (media.virus_scan_enabled=false) — mark the blob safe
                    // immediately so dedup still works instead of every
                    // re-upload of identical content permanently missing
                    // the dedup query above (which only matches 'safe').
                    $virusScanEnabled = config('media.virus_scan_enabled', true);
                    $initialVirusStatus = $virusScanEnabled ? 'pending' : 'safe';

                    try {
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
                            'virus_status' => $initialVirusStatus,
                            'uploaded_at' => now(),
                        ]);

                        $media->media_blob_id = $blob->id;
                        $media->save();
                    } catch (QueryException $e) {
                        // Another concurrent confirm for the same tenant+hash
                        // won the unique (tenant_id, sha256) constraint race.
                        // Fall back to linking against whatever it created
                        // instead of surfacing a raw 500 to this request.
                        if ($e->getCode() !== '23000') {
                            throw $e;
                        }

                        $winningBlob = MediaBlob::query()
                            ->where('tenant_id', $tenantId)
                            ->where('sha256', $actualHash)
                            ->first();

                        if (! $winningBlob) {
                            throw $e;
                        }

                        $media->media_blob_id = $winningBlob->id;
                        $media->save();
                        $disk->delete($storagePath);
                    }
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
            // Any failure inside the transaction above rolls the DB back to
            // Pending — surface it as a terminal Failed state with a reason
            // instead of leaving the media stuck in Pending with no
            // visibility into what went wrong (previously only
            // checksum_mismatch was handled here).
            if (in_array($e->errorCode, ['checksum_mismatch', 'file_not_found'], true)) {
                $media = Media::query()->findOrFail($mediaId);

                if (in_array($media->status, [MediaStatus::Pending, MediaStatus::Uploading], true)) {
                    $this->stateMachine->transition($media, MediaStatus::Failed);
                    $media->update([
                        'processing_error' => $e->errorCode === 'checksum_mismatch'
                            ? __('media.checksum_failed')
                            : $e->getMessage(),
                    ]);
                }
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
        $maxSizeVal = config('media.max_file_size_bytes', 500 * 1024 * 1024);
        $maxSize = is_numeric($maxSizeVal) ? (int) $maxSizeVal : 500 * 1024 * 1024;
        if ($file->getSize() > $maxSize) {
            throw new DomainException(__('media.file_too_large'), errorCode: 'file_too_large');
        }

        $allowedMimesConfig = config('media.allowed_mimes', []);
        $allowedMimes = is_array($allowedMimesConfig) ? $allowedMimesConfig : [];
        $mimeType = (string) $file->getMimeType();
        if (! in_array($mimeType, $allowedMimes, true)) {
            throw new DomainException(__('media.disallowed_mime_type', ['mime' => $mimeType]), errorCode: 'disallowed_mime_type');
        }

        $mediaType = $this->determineMediaType($mimeType);
        $mediaId = (string) Str::uuid();

        $sanitizedName = (string) preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($file->getClientOriginalName()));
        $extension = pathinfo($sanitizedName, PATHINFO_EXTENSION);

        $defaultDiskVal = config('media.default_disk', 'public');
        $privateDiskVal = config('media.private_disk', 'local');
        $defaultDisk = is_string($defaultDiskVal) ? $defaultDiskVal : 'public';
        $privateDisk = is_string($privateDiskVal) ? $privateDiskVal : 'local';
        $diskName = $isPublic ? $defaultDisk : $privateDisk;

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
