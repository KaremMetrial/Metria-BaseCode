<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Policies;

use App\Domain\Auth\Models\User;
use App\Domain\Webhook\Models\WebhookEndpoint;
use Illuminate\Auth\Access\HandlesAuthorization;

class WebhookEndpointPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('admin.super')) {
            return true;
        }

        return null;
    }

    public function manage(User $user): bool
    {
        return $user->can('webhooks.manage');
    }
}
