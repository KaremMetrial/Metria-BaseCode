<?php

declare(strict_types=1);

namespace Tests\Feature\RBAC\Infrastructure;

use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesPermission;
use Tests\Support\CreatesRole;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant, CreatesRole, CreatesPermission, CreatesUser;

    public function test_effective_permissions_endpoint_performance(): void
    {
        $tenant = $this->setRandomTenant();
        $user = $this->createUser($tenant);
        
        // Seed some volume (not a full 1000 for standard test suite speed, but enough to catch N+1)
        // For a real stress test, this would be higher, but we want tests to remain fast.
        // We'll do 10 roles, each with 10 permissions (100 perms total).
        for ($i = 0; $i < 10; $i++) {
            $role = $this->createRole($tenant, ['name' => "Perf Role $i"]);
            for ($j = 0; $j < 10; $j++) {
                $perm = $this->createPermission(['name' => "perf.perm.{$i}.{$j}"]);
                $role->givePermissionTo($perm);
            }
            $user->assignRole($role);
        }
        $permView = $this->createPermission(['name' => 'rbac.permissions.view']);
        $user->givePermissionTo($permView);

        $this->actingAs($user);

        // Warm up cache
        $this->getJson("/api/v1/rbac/users/{$user->id}/effective-permissions");

        // Now measure queries for a cached hit (should be 0 or very few for auth)
        // We expect mostly just the User lookup and maybe Session/Tenant resolution.
        // The Spatie permission check should be entirely cached.
        
        $initialQueryCount = DB::getQueryLog() ? count(DB::getQueryLog()) : 0;
        
        DB::enableQueryLog();
        $response = $this->getJson("/api/v1/rbac/users/{$user->id}/effective-permissions");
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        
        // Assert no N+1 queries. It should be less than 5 queries typically.
        $this->assertLessThan(10, $queryCount, "Effective permissions endpoint executed {$queryCount} queries, possible N+1.");
    }
}
