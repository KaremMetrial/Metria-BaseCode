<?php

declare(strict_types=1);

namespace Tests\Feature\RBAC\Authorization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesRole;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class AuthorizationContractTest extends TestCase
{
    use CreatesRole, CreatesTenant, CreatesUser, \Tests\Support\CreatesPermission;
    use RefreshDatabase;

    public function test_all_management_endpoints_require_rbac_roles_manage_permission(): void
    {
        $tenant = $this->setRandomTenant();
        $this->createPermission(['name' => 'rbac.roles.manage']);

        $userWithoutPermission = $this->createUser($tenant);

        $role = $this->createRole($tenant);

        $this->actingAs($userWithoutPermission);

        // CREATE
        $this->postJson('/api/v1/rbac/roles', [
            'name' => 'Should Fail',
        ])->assertForbidden();

        // UPDATE
        $this->putJson("/api/v1/rbac/roles/{$role->id}", [
            'name' => 'Should Fail',
        ])->assertForbidden();

        // DELETE
        $this->deleteJson("/api/v1/rbac/roles/{$role->id}")->assertForbidden();
    }
}
