<?php

declare(strict_types=1);

namespace Modules\RBAC\Infrastructure\Providers;

use Modules\RBAC\Domain\Models\Role;
use Modules\RBAC\Presentation\Policies\RolePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class RbacAuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Role::class => RolePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
