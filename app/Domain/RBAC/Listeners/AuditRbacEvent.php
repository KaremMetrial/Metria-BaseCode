<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Listeners;

use App\Domain\Governance\Services\AuditLogger;
use App\Domain\RBAC\Events\RoleCreated;
use App\Domain\RBAC\Events\RoleDeleted;
use App\Domain\RBAC\Events\RolePermissionsUpdated;
use App\Domain\RBAC\Events\UserRolesUpdated;
use Illuminate\Events\Dispatcher;

class AuditRbacEvent
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(RoleCreated::class, [$this, 'handleRoleCreated']);
        $events->listen(RoleDeleted::class, [$this, 'handleRoleDeleted']);
        $events->listen(RolePermissionsUpdated::class, [$this, 'handleRolePermissionsUpdated']);
        $events->listen(UserRolesUpdated::class, [$this, 'handleUserRolesUpdated']);
    }

    public function handleRoleCreated(RoleCreated $event): void
    {
        $this->auditLogger->log(
            action: 'created',
            auditable: $event->role,
            newValues: [
                'name' => $event->role->name,
                'guard_name' => $event->role->guard_name,
                'tenant_id' => $event->role->tenant_id,
            ],
        );
    }

    public function handleRoleDeleted(RoleDeleted $event): void
    {
        $this->auditLogger->log('deleted', null, $event->payload());
    }

    public function handleRolePermissionsUpdated(RolePermissionsUpdated $event): void
    {
        $this->auditLogger->log('rbac.role_permissions_updated', null, context: $event->payload());
    }

    public function handleUserRolesUpdated(UserRolesUpdated $event): void
    {
        $this->auditLogger->log('rbac.user_roles_updated', null, context: $event->payload());
    }
}

