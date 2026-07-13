<?php

declare(strict_types=1);

namespace Tests\Feature\RBAC\Infrastructure;

use Tests\Support\CreatesPermission;
use Tests\Support\CreatesRole;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotencyAndTransactionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant, CreatesRole, CreatesPermission, CreatesUser;

    public function test_assigning_roles_is_idempotent(): void
    {
        $tenant = $this->setRandomTenant();
        $admin = $this->createUser($tenant);
        $admin->givePermissionTo($this->createPermission(['name' => 'rbac.roles.manage']));
        
        $targetUser = $this->createUser($tenant);
        $role = $this->createRole($tenant, ['name' => 'Idempotent Role']);

        $this->actingAs($admin);

        // First assignment
        $response1 = $this->postJson("/api/v1/rbac/users/{$targetUser->id}/roles", [
            'roles' => [$role->name]
        ]);
        $response1->assertOk();

        // Count pivot rows directly to ensure no duplicates
        $count1 = \DB::table('model_has_roles')
            ->where('model_id', $targetUser->id)
            ->where('role_id', $role->id)
            ->count();
            
        $this->assertEquals(1, $count1);

        // Second assignment (identical)
        $response2 = $this->postJson("/api/v1/rbac/users/{$targetUser->id}/roles", [
            'roles' => [$role->name]
        ]);
        $response2->assertOk();

        $count2 = \DB::table('model_has_roles')
            ->where('model_id', $targetUser->id)
            ->where('role_id', $role->id)
            ->count();
            
        $this->assertEquals(1, $count2, 'Idempotency failed: duplicate assignment created in pivot table.');
    }
}
