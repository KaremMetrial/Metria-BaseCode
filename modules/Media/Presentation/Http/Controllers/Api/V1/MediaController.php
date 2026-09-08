<?php

declare(strict_types=1);

namespace Modules\Media\Presentation\Http\Controllers\Api\V1;

use Modules\Shared\Presentation\Http\Controllers\ApiController;
use Modules\Media\Presentation\Http\Requests\ConfirmUploadRequest;
use Modules\Media\Presentation\Http\Requests\GeneratePresignedUrlRequest;
use Modules\Media\Presentation\Http\Resources\MediaResource;
use Modules\Media\Domain\Models\Media;
use Modules\Media\Infrastructure\Services\MediaDownloadService;
use Modules\Media\Infrastructure\Services\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MediaController extends ApiController
{
    public function presign(GeneratePresignedUrlRequest $request, MediaUploadService $uploadService): JsonResponse
    {
        $sizeVal = $request->input('size');
        $size = is_numeric($sizeVal) ? (int) $sizeVal : 0;

        $result = $uploadService->initiateUpload(
            user: $this->getAuthenticatedUser($request),
            filename: $request->string('filename')->value(),
            mimeType: $request->string('mime_type')->value(),
            size: $size,
            isPublic: (bool) $request->input('is_public', false),
            purpose: $request->string('purpose', 'attachment')->value()
        );

        return $this->respond($result);
    }

    public function confirm(ConfirmUploadRequest $request, string $mediaId, MediaUploadService $uploadService): JsonResponse
    {
        // Tenant scoping alone (TenantScope) doesn't stop one user from
        // confirming another user's upload within the same tenant — check
        // ownership of this specific media explicitly.
        $pendingMedia = Media::query()->findOrFail($mediaId);
        Gate::authorize('confirm', $pendingMedia);

        $media = $uploadService->confirmUpload(
            mediaId: $mediaId,
            clientChecksum: $request->string('checksum')->value()
        );

        return $this->respond(new MediaResource($media));
    }

    /**
     * Local-disk fallback for the presign flow: accepts the raw file body
     * PUT here (mirroring what a real presigned S3 PUT URL would receive)
     * and writes it to the pre-computed storage path, so confirm() has an
     * actual file to find. Only reachable for the upload_url initiateUpload()
     * hands out when the disk lacks a real temporaryUploadUrl() adapter.
     */
    public function upload(Request $request, string $mediaId, MediaUploadService $uploadService): JsonResponse
    {
        $media = Media::query()->findOrFail($mediaId);
        Gate::authorize('confirm', $media);

        $uploadService->receiveLocalUpload($media, $request->getContent());

        return $this->respond(['status' => 'received']);
    }

    public function download(string $mediaId, MediaDownloadService $downloadService): JsonResponse
    {
        /** @var Media $media */
        $media = Media::query()->findOrFail($mediaId);

        Gate::authorize('download', $media);

        $url = $downloadService->generateDownloadUrl($media);

        return $this->respond([
            'download_url' => $url,
        ]);
    }

    private function getAuthenticatedUser(Request $request): \Modules\Auth\Domain\Models\User
    {
        $user = $request->user();
        if (! $user instanceof \Modules\Auth\Domain\Models\User) {
            throw new \Modules\Shared\Application\Exceptions\ApiException(__('auth.unauthorized', ['default' => 'Unauthorized']), status: 401, errorCode: 'unauthorized');
        }

        return $user;
    }
}
