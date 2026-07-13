<?php

declare(strict_types=1);

namespace Tests\Feature\RBAC\Lifecycle;

use App\Domain\RBAC\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\Support\CreatesPermission;
use Tests\Support\CreatesRole;
use Tests\Support\CreatesTenant;
use Tests\Support\CreatesUser;
use Tests\TestCase;

class MutationResistanceTest extends TestCase
{
    use CreatesPermission, CreatesRole, CreatesTenant, CreatesUser;
    use RefreshDatabase;

    public function test_sync_command_repairs_deleted_registered_permissions(): void
    {
        // 1. Suppose a permission is defined in the Registry and synced.
        // We simulate this by creating a permission that matches a registry entry.
        // We'll just use a real one from the registry.
        $registryPerms = PermissionRegistry::all();
        $targetPerm = $registryPerms[0] ?? 'rbac.roles.view';

        Permission::firstOrCreate(['name' => $targetPerm, 'guard_name' => 'web']);

        // 2. Simulate DB corruption or accidental manual deletion
        Permission::where('name', $targetPerm)->delete();
        $this->assertNull(Permission::where('name', $targetPerm)->first());

        // 3. System Sync repairs state gracefully
        Artisan::call('rbac:permissions:sync', ['--guard' => 'web']);

        // 4. Assert repaired
        $this->assertNotNull(Permission::where('name', $targetPerm)->first());
    }
}
