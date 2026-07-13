<?php

declare(strict_types=1);

namespace Tests\Feature\RBAC\Infrastructure;

use App\Domain\RBAC\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RegistryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_command_removes_unregistered_permissions(): void
    {
        Permission::create(['name' => 'legacy.permission', 'guard_name' => 'web']);

        $this->assertNotNull(Permission::where('name', 'legacy.permission')->first());

        // Assuming 'legacy.permission' is NOT in PermissionRegistry
        $this->assertNotContains('legacy.permission', PermissionRegistry::all());

        Artisan::call('rbac:permissions:sync', ['--guard' => 'web']);

        $this->assertNull(Permission::where('name', 'legacy.permission')->first());
    }

    public function test_sync_command_adds_missing_registered_permissions(): void
    {
        // 1. Delete a known permission
        $registered = PermissionRegistry::all();
        $target = $registered[0] ?? 'rbac.roles.manage';

        Permission::where('name', $target)->delete();
        $this->assertNull(Permission::where('name', $target)->first());

        // 2. Sync
        Artisan::call('rbac:permissions:sync', ['--guard' => 'web']);

        // 3. Assert recreated
        $this->assertNotNull(Permission::where('name', $target)->first());
    }

    public function test_sync_command_respects_guard(): void
    {
        // Create an api permission
        Permission::create(['name' => 'api.only', 'guard_name' => 'api']);

        // Syncing web guard should NOT touch api guard permissions
        Artisan::call('rbac:permissions:sync', ['--guard' => 'web']);

        $this->assertNotNull(Permission::where('name', 'api.only')->where('guard_name', 'api')->first());
    }
}
