<?php

declare(strict_types=1);

namespace Tests\Feature\RBAC\Authorization;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesPermission;
use Tests\Support\CreatesRole;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EffectivePermissionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTenant, CreatesRole, CreatesPermission, CreatesUser;

    /**
     * @dataProvider effectivePermissionMatrix
     */
    public function test_effective_permissions_matrix(
        array $rolePermissions,
        array $directPermissions,
        array $expectedEffectivePermissions
    ): void {
        $tenant = $this->setRandomTenant();
        
        $user = $this->createUser($tenant);
        $role = $this->createRole($tenant, ['name' => 'Matrix Role']);

        // Create and assign role permissions
        foreach ($rolePermissions as $permName) {
            $perm = $this->createPermission(['name' => $permName]);
            $role->givePermissionTo($perm);
        }
        
        $user->assignRole($role);

        // Create and assign direct permissions
        foreach ($directPermissions as $permName) {
            $perm = $this->createPermission(['name' => $permName]);
            $user->givePermissionTo($perm);
        }

        $this->actingAs($user);

        $response = $this->getJson("/api/v1/rbac/users/{$user->id}/effective-permissions");
        $response->assertOk();

        $actualPermissions = collect($response->json('data.source_map'))->keys()->toArray();

        sort($expectedEffectivePermissions);
        sort($actualPermissions);

        $this->assertEquals($expectedEffectivePermissions, $actualPermissions);
    }

    public static function effectivePermissionMatrix(): array
    {
        return [
            'Only Role Permissions' => [
                'rolePermissions' => ['perm.a', 'perm.b'],
                'directPermissions' => [],
                'expectedEffectivePermissions' => ['perm.a', 'perm.b'],
            ],
            'Only Direct Permissions' => [
                'rolePermissions' => [],
                'directPermissions' => ['perm.c'],
                'expectedEffectivePermissions' => ['perm.c'],
            ],
            'Combined Permissions' => [
                'rolePermissions' => ['perm.a'],
                'directPermissions' => ['perm.b'],
                'expectedEffectivePermissions' => ['perm.a', 'perm.b'],
            ],
            'Overlapping Permissions' => [
                'rolePermissions' => ['perm.a', 'perm.b'],
                'directPermissions' => ['perm.b', 'perm.c'],
                'expectedEffectivePermissions' => ['perm.a', 'perm.b', 'perm.c'], // Should deduplicate
            ],
        ];
    }
}
