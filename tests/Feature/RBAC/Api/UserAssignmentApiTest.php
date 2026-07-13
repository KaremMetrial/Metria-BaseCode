<?php

declare(strict_types=1);

namespace Tests\Feature\RBAC\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesPermission;
use Tests\Support\CreatesRole;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class UserAssignmentApiTest extends TestCase
{
    use CreatesPermission, CreatesRole, CreatesTenant, CreatesUser;
    use RefreshDatabase;

    public function test_it_assigns_role_to_user_successfully(): void
    {
        $tenant = $this->setRandomTenant();
        $admin = $this->createUser($tenant);

        $managePerm = $this->createPermission(['name' => 'rbac.roles.manage']);
        $admin->givePermissionTo($managePerm);

        $targetUser = $this->createUser($tenant);
        $role = $this->createRole($tenant, ['name' => 'Manager']);

        $this->actingAs($admin);

        $response = $this->postJson("/api/v1/rbac/users/{$targetUser->id}/roles", [
            'roles' => [$role->name],
        ]);

        $response->assertOk();

        // Assert user has role
        $this->assertTrue($targetUser->fresh()->hasRole('Manager'));
    }

    public function test_it_rejects_assignment_of_nonexistent_role(): void
    {
        $tenant = $this->setRandomTenant();
        $admin = $this->createUser($tenant);

        $managePerm = $this->createPermission(['name' => 'rbac.roles.manage']);
        $admin->givePermissionTo($managePerm);

        $targetUser = $this->createUser($tenant);

        $this->actingAs($admin);

        $response = $this->postJson("/api/v1/rbac/users/{$targetUser->id}/roles", [
            'roles' => ['DoesNotExist'],
        ]);

        $this->assertEquals('The selected roles.0 is invalid.', $response->json('error.errors')['roles.0'][0]);
    }
}
