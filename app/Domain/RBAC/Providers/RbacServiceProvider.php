<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Providers;

use App\Domain\RBAC\Console\Commands\SyncPermissionsCommand;
use App\Domain\RBAC\Contracts\PermissionRepositoryInterface;
use App\Domain\RBAC\Contracts\RoleRepositoryInterface;
use App\Domain\RBAC\Listeners\AuditRbacEvent;
use App\Domain\RBAC\Listeners\ClearRbacCache;
use App\Domain\RBAC\Repositories\PermissionRepository;
use App\Domain\RBAC\Repositories\RoleRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class RbacServiceProvider extends ServiceProvider
{
    public array $bindings = [
        RoleRepositoryInterface::class => RoleRepository::class,
        PermissionRepositoryInterface::class => PermissionRepository::class,
    ];

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncPermissionsCommand::class,
            ]);
        }

        // Register event subscribers
        Event::subscribe(AuditRbacEvent::class);
        Event::subscribe(ClearRbacCache::class);
    }
}
