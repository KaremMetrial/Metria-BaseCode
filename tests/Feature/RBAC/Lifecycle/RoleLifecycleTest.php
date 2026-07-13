<?php

declare(strict_types=1);

namespace Tests\Feature\RBAC\Lifecycle;

use App\Domain\RBAC\Models\Role;
use Tests\Support\CreatesPermission;
use Tests\Support\CreatesRole;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleLifecycleTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant, CreatesRole, CreatesPermission, CreatesUser;

    public function test_role_end_to_end_lifecycle(): void
    {
        $tenant = $this->setRandomTenant();
        $admin = $this->createUser($tenant);
        
        $managePerm = $this->createPermission(['name' => 'rbac.roles.manage']);
        $admin->givePermissionTo($managePerm);
        $this->actingAs($admin);

        // 1. Create Role
        $createResponse = $this->postJson('/api/v1/rbac/roles', [
            'name' => 'Content Manager',
            'display_name' => ['en' => 'Content Manager'],
            'description' => ['en' => 'Manages CMS'],
        ]);
        
        $createResponse->assertCreated();
        $roleId = $createResponse->json('data.id');

        // 2. Update Role
        $updateResponse = $this->putJson("/api/v1/rbac/roles/{$roleId}", [
            'name' => 'Senior Content Manager',
        ]);
        
        $updateResponse->assertOk();
        $this->assertEquals('Senior Content Manager', $updateResponse->json('data.name'));

        // 3. Assign Role to User
        $targetUser = $this->createUser($tenant);

        $assignResponse = $this->postJson("/api/v1/rbac/users/{$targetUser->id}/roles", [
            'roles' => ['Senior Content Manager']
        ]);
        
        $assignResponse->assertOk();
        $this->assertTrue($targetUser->fresh()->hasRole('Senior Content Manager'));

        // 4. Revoke Role (Remove specific role)
        $revokeResponse = $this->deleteJson("/api/v1/rbac/users/{$targetUser->id}/roles", [
            'roles' => ['Senior Content Manager']
        ]);
        
        $revokeResponse->assertOk();
        $this->assertFalse($targetUser->fresh()->hasRole('Senior Content Manager'));

        // 5. Delete Role
        $deleteResponse = $this->deleteJson("/api/v1/rbac/roles/{$roleId}");
        
        $deleteResponse->assertOk();
        $this->assertNull(Role::find($roleId));
    }
}
