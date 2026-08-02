<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Events;

use Modules\Shared\Domain\Events\DomainEvent;

class RoleDeleted extends DomainEvent
{
    public function __construct(public readonly int|string $roleId, public readonly string $roleName)
    {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'rbac.role.deleted';
    }

    public function payload(): array
    {
        return [
            'role_id' => $this->roleId,
            'role_name' => $this->roleName,
        ];
    }
}
