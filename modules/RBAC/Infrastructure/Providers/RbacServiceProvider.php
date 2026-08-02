<?php

declare(strict_types=1);

namespace Modules\RBAC\Infrastructure\Providers;

use Modules\RBAC\Infrastructure\Console\Commands\SyncPermissionsCommand;
use Modules\RBAC\Domain\Contracts\PermissionRepositoryInterface;
use Modules\RBAC\Domain\Contracts\RoleRepositoryInterface;
use Modules\RBAC\Infrastructure\Listeners\AuditRbacEvent;
use Modules\RBAC\Infrastructure\Listeners\ClearRbacCache;
use Modules\RBAC\Domain\Models\RoleMetadata;
use Modules\RBAC\Infrastructure\Repositories\PermissionRepository;
use Modules\RBAC\Infrastructure\Repositories\RoleRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Infrastructure\Translation\TranslationRegistry;

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

        $this->app->make(TranslationRegistry::class)->register(RoleMetadata::class);

        $this->loadRoutesFrom(__DIR__.'/../../Presentation/routes/api.php');
    }
}
