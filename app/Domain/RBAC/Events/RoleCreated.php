<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Events;

use App\Core\Events\DomainEvent;
use App\Domain\RBAC\Models\Role;

class RoleCreated extends DomainEvent
{
    public function __construct(public readonly Role $role)
    {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'rbac.role.created';
    }

    public function payload(): array
    {
        return [
            'role_id' => $this->role->id,
            'role_name' => $this->role->name,
            'tenant_id' => $this->role->tenant_id,
        ];
    }
}
