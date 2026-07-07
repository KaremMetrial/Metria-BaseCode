<?php

declare(strict_types=1);

namespace App\Domain\Media\Services;

use App\Core\Exceptions\DomainException;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\Media;

class MediaStateMachine
{
    /**
     * Map of allowed transitions.
     * key: current status
     * values: array of permitted target statuses
     */
    private const ALLOWED_TRANSITIONS = [
        MediaStatus::Pending->value => [
            MediaStatus::Uploading->value,
            MediaStatus::Uploaded->value,
            MediaStatus::Failed->value,
            MediaStatus::Deleted->value,
        ],
        MediaStatus::Uploading->value => [
            MediaStatus::Uploaded->value,
            MediaStatus::Failed->value,
            MediaStatus::Deleted->value,
        ],
        MediaStatus::Uploaded->value => [
            MediaStatus::Verifying->value,
            MediaStatus::Failed->value,
            MediaStatus::Deleted->value,
        ],
        MediaStatus::Verifying->value => [
            MediaStatus::Processing->value,
            MediaStatus::Quarantined->value,
            MediaStatus::Failed->value,
            MediaStatus::Deleted->value,
        ],
        MediaStatus::Processing->value => [
            MediaStatus::Active->value,
            MediaStatus::Quarantined->value,
            MediaStatus::Failed->value,
            MediaStatus::Deleted->value,
        ],
        MediaStatus::Active->value => [
            MediaStatus::Deleted->value,
        ],
        MediaStatus::Quarantined->value => [
            MediaStatus::Active->value, // Allow restoration by admin
            MediaStatus::Deleted->value,
        ],
        MediaStatus::Failed->value => [
            MediaStatus::Pending->value, // Allow retries
            MediaStatus::Deleted->value,
        ],
        MediaStatus::Deleted->value => [],
    ];

    public function transition(Media $media, MediaStatus $newStatus): void
    {
        $current = $media->status;

        // If the state is already the target, return early (idempotent)
        if ($current === $newStatus) {
            return;
        }

        $allowed = self::ALLOWED_TRANSITIONS[$current->value] ?? [];

        if (! in_array($newStatus->value, $allowed, true)) {
            throw new DomainException(
                "Illegal media status transition from [{$current->value}] to [{$newStatus->value}].",
                errorCode: 'invalid_status_transition'
            );
        }

        $media->status = $newStatus;

        // Sync operational timestamps based on state
        $now = now();
        switch ($newStatus) {
            case MediaStatus::Processing:
                $media->processing_started_at = $now;
                break;
            case MediaStatus::Active:
                $media->activated_at = $now;
                $media->processing_finished_at = $now;
                break;
            case MediaStatus::Quarantined:
                $media->quarantined_at = $now;
                break;
        }

        $media->save();
    }
}
