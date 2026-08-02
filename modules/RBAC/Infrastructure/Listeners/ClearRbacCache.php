<?php

declare(strict_types=1);

namespace Modules\RBAC\Infrastructure\Listeners;

use Modules\RBAC\Domain\Events\RolePermissionsUpdated;
use Modules\RBAC\Domain\Events\UserRolesUpdated;
use Modules\RBAC\Infrastructure\Support\AuthorizationCache;
use Illuminate\Events\Dispatcher;

class ClearRbacCache
{
    public function __construct(private readonly AuthorizationCache $cache) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(RolePermissionsUpdated::class, [$this, 'handle']);
        $events->listen(UserRolesUpdated::class, [$this, 'handle']);
    }

    public function handle(mixed $event): void
    {
        $this->cache->flush();
    }
}
