<?php

declare(strict_types=1);

namespace Tests\Feature\RBAC\Api;

use Tests\Support\CreatesPermission;
use Tests\Support\CreatesRole;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant, CreatesRole, CreatesPermission, CreatesUser;

    public function test_it_creates_role_with_translations(): void
    {
        $tenant = $this->setRandomTenant();
        $user = $this->createUser($tenant);
        
        $managePerm = $this->createPermission(['name' => 'rbac.roles.manage']);
        $user->givePermissionTo($managePerm);

        $this->actingAs($user);

        $response = $this->postJson('/api/v1/rbac/roles', [
            'name' => 'editor',
            'display_name' => [
                'en' => 'Editor',
                'ar' => 'محرر',
            ],
            'description' => [
                'en' => 'Can edit content',
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'editor');
        $response->assertJsonPath('data.display_name.ar', 'محرر');
    }

    public function test_it_returns_422_on_invalid_data(): void
    {
        $tenant = $this->setRandomTenant();
        $user = $this->createUser($tenant);
        
        $managePerm = $this->createPermission(['name' => 'rbac.roles.manage']);
        $user->givePermissionTo($managePerm);

        $this->actingAs($user);

        // Missing required 'name'
        $response = $this->postJson('/api/v1/rbac/roles', [
            'display_name' => ['en' => 'Editor'],
        ]);
        $response->assertUnprocessable();
        $response->assertJsonPath('error.errors.name.0', 'The name field is required.');
    }
}
