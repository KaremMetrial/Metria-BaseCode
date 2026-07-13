<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Events;

use App\Core\Events\DomainEvent;
use App\Domain\Auth\Models\User;

class UserRolesUpdated extends DomainEvent
{
    /**
     * @param  array<int, string>  $roles
     */
    public function __construct(public readonly User $user, public readonly array $roles)
    {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'rbac.user.roles.updated';
    }

    public function payload(): array
    {
        return [
            'user_id' => $this->user->id,
            'roles_count' => count($this->roles),
            'roles' => $this->roles,
        ];
    }
}
