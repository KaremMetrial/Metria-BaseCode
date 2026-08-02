<?php

declare(strict_types=1);

namespace Modules\RBAC\Infrastructure\Listeners;

use Modules\Governance\Infrastructure\Services\AuditLogger;
use Modules\RBAC\Domain\Events\RoleCreated;
use Modules\RBAC\Domain\Events\RoleDeleted;
use Modules\RBAC\Domain\Events\RolePermissionsUpdated;
use Modules\RBAC\Domain\Events\UserRolesUpdated;
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
