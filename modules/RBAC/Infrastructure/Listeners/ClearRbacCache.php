<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Listeners;

use App\Domain\RBAC\Events\RolePermissionsUpdated;
use App\Domain\RBAC\Events\UserRolesUpdated;
use App\Domain\RBAC\Support\AuthorizationCache;
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
