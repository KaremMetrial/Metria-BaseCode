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

    public function view(User $user, Media $media): bool
    {
        // Public files can be viewed by anyone authenticated.
        if ($media->is_public) {
            return true;
        }

        // Owners can view.
        if ((string) $media->created_by === (string) $user->id) {
            return true;
        }

        // If it is attached to a mediable model, check ownership or specific permissions.
        return $user->can('media.view');
    }

    public function download(User $user, Media $media): bool
    {
        return $this->view($user, $media);
    }

    public function delete(User $user, Media $media): bool
    {
        return (string) $media->created_by === (string) $user->id || $user->can('media.delete');
    }
}
