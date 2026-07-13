<?php

declare(strict_types=1);

namespace Tests\Feature\RBAC\Authorization;

use App\Domain\RBAC\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\Support\CreatesPermission;
use Tests\Support\CreatesRole;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant, CreatesRole, CreatesPermission, CreatesUser;

    public function test_tenant_cannot_see_roles_belonging_to_another_tenant(): void
    {
        $tenantA = $this->setRandomTenant();
        $this->createRole($tenantA, ['name' => 'Role A']);

        $tenantB = $this->setRandomTenant();
        $this->createRole($tenantB, ['name' => 'Role B']);

        // Set active tenant back to A
        $this->setTenant($tenantA);

        $roles = Role::whereNotNull('tenant_id')->get();
        
        $this->assertCount(1, $roles);
        $this->assertEquals('Role A', $roles->first()->name);
    }

    public function test_tenant_can_see_global_system_roles(): void
    {
        $tenantA = $this->setRandomTenant();
        
        // System roles have null tenant_id
        $this->createSystemRole('System Admin');
        
        $this->createRole($tenantA, ['name' => 'Custom Role']);

        $roles = Role::all();
        
        // It will contain our created role, our custom system role, plus seeded system roles
        $this->assertTrue($roles->contains('name', 'System Admin'));
        $this->assertTrue($roles->contains('name', 'Custom Role'));
        
        // Tenant A should only have 1 scoped role
        $this->assertEquals(1, $roles->whereNotNull('tenant_id')->count());
    }

    public function test_super_admin_bypasses_authorization_but_not_tenant_isolation(): void
    {
        $tenantA = $this->setRandomTenant();
        $superAdmin = $this->createUser($tenantA);
        
        // Temporarily disable scope to assign system role
        Role::withoutGlobalScopes()->get();
        $superRole = $this->createSystemRole('super-admin');
        $superAdmin->assignRole($superRole);

        $tenantB = $this->setRandomTenant();
        $roleB = $this->createRole($tenantB, ['name' => 'Tenant B Role']);

        $this->actingAs($superAdmin);

        // Super Admin in Tenant A tries to hit API to view Tenant B's role
        $response = $this->getJson("/api/v1/rbac/roles/{$roleB->id}");
        
        // Should be 404, not 403. The record is completely invisible to Tenant A's scope.
        $response->assertNotFound();
    }
}
