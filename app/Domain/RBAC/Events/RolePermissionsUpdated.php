<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Events;

use App\Core\Events\DomainEvent;
use App\Domain\RBAC\Models\Role;

class RolePermissionsUpdated extends DomainEvent
{
    /**
     * @param array<int, string> $permissions
     */
    public function __construct(public readonly Role $role, public readonly array $permissions)
    {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'rbac.role.permissions.updated';
    }

    public function payload(): array
    {
        return [
            'role_id' => $this->role->id,
            'role_name' => $this->role->name,
            'permissions_count' => count($this->permissions),
            'permissions' => $this->permissions,
        ];
    }
}
