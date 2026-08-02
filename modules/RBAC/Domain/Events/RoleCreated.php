<?php

declare(strict_types=1);

namespace Modules\RBAC\Domain\Events;

use Modules\Shared\Domain\Events\DomainEvent;
use Modules\RBAC\Domain\Models\Role;

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
