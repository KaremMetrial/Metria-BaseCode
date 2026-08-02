<?php

declare(strict_types=1);

namespace App\Domain\Media\Policies;

use App\Domain\Auth\Models\User;
use App\Domain\Media\Models\Media;
use Illuminate\Auth\Access\HandlesAuthorization;

class MediaPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('admin.super')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('media.upload') || $user->can('media.manage');
    }

    public function view(User $user, ?Media $media = null): bool
    {
        if ($media === null) {
            return true;
        }

        // Public files can be viewed by anyone authenticated.
        if ($media->is_public) {
            return true;
        }

        // Owners can view.
        if ((string) $media->created_by === (string) $user->id) {
            return true;
        }

        // For private files owned by others, only managers can view.
        return $user->can('media.manage');
    }

    public function download(User $user, ?Media $media = null): bool
    {
        return $this->view($user, $media);
    }

    public function delete(User $user, ?Media $media = null): bool
    {
        if ($media === null) {
            return $user->can('media.delete');
        }

        return (string) $media->created_by === (string) $user->id || $user->can('media.delete');
    }
}
