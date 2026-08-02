<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Providers;

use App\Domain\RBAC\Models\Role;
use App\Domain\RBAC\Policies\RolePolicy;
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
