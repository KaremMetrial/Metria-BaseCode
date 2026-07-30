<?php

declare(strict_types=1);

namespace Modules\Webhook\Presentation\Policies;

// TODO: update when IAM module lands
use App\Domain\Auth\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Webhook\Domain\Models\WebhookEndpoint;

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

    public function viewAny(User $user): bool
    {
        return $user->can('webhooks.view') || $user->can('webhooks.manage');
    }

    public function view(User $user, ?WebhookEndpoint $endpoint = null): bool
    {
        return $user->can('webhooks.view') || $user->can('webhooks.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('webhooks.manage');
    }

    public function update(User $user, ?WebhookEndpoint $endpoint = null): bool
    {
        return $user->can('webhooks.manage');
    }

    public function delete(User $user, ?WebhookEndpoint $endpoint = null): bool
    {
        return $user->can('webhooks.manage');
    }

    public function manage(User $user): bool
    {
        return $user->can('webhooks.manage');
    }
}
