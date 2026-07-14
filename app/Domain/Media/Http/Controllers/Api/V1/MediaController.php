<?php

declare(strict_types=1);

namespace App\Domain\Media\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Media\Http\Requests\ConfirmUploadRequest;
use App\Domain\Media\Http\Requests\GeneratePresignedUrlRequest;
use App\Domain\Media\Http\Resources\MediaResource;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Services\MediaDownloadService;
use App\Domain\Media\Services\MediaUploadService;
use Illuminate\Http\JsonResponse;
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
        // Enforce ownership / tenant scoping (inherent in Media query through tenant global scope)
        $media = $uploadService->confirmUpload(
            mediaId: $mediaId,
            clientChecksum: $request->string('checksum')->value()
        );

        return $this->respond(new MediaResource($media));
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

    private function getAuthenticatedUser(\Illuminate\Http\Request $request): \App\Domain\Auth\Models\User
    {
        $user = $request->user();
        if (! $user instanceof \App\Domain\Auth\Models\User) {
            throw new \App\Core\Exceptions\ApiException(__('auth.unauthorized', ['default' => 'Unauthorized']), status: 401, errorCode: 'unauthorized');
        }

        return $user;
    }
}
