<?php

declare(strict_types=1);

namespace Tests\Feature\RBAC\Lifecycle;

use App\Domain\RBAC\Support\AuthorizationCache;
use Tests\Support\CreatesPermission;
use Tests\Support\CreatesRole;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecoveryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant, CreatesRole, CreatesPermission, CreatesUser;

    public function test_system_recovers_gracefully_if_cache_purged(): void
    {
        $tenant = $this->setRandomTenant();
        $user = $this->createUser($tenant);
        
        $role = $this->createRole($tenant, ['name' => 'Cache Test Role']);
        $perm = $this->createPermission(['name' => 'rbac.permissions.view']);
        $role->givePermissionTo($perm);
        $user->assignRole($role);

        // 1. Initial request populates cache and works
        $this->actingAs($user);
        $this->getJson("/api/v1/rbac/users/{$user->id}/effective-permissions")->assertOk();

        // 2. Simulate catastrophic cache purge (Redis eviction, restart, etc.)
        app(AuthorizationCache::class)->flush();

        // 3. System should recover on next request without crashing
        $response = $this->getJson("/api/v1/rbac/users/{$user->id}/effective-permissions");
        
        $response->assertOk();
    }
}
