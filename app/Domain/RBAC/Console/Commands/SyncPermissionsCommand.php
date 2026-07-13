<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Console\Commands;

use App\Domain\RBAC\Support\AuthorizationCache;
use App\Domain\RBAC\Support\PermissionRegistry;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'rbac:permissions:sync {--guard= : The guard to sync permissions for}';

    protected $description = 'Sync permissions from the PermissionRegistry to the database';

    public function handle(AuthorizationCache $cache): int
    {
        $guard = $this->option('guard') ?: config('auth.defaults.guard', 'web');
        $this->info("Syncing permissions from PermissionRegistry for guard: {$guard}...");

        $registeredPermissions = PermissionRegistry::all();
        $existingPermissions = Permission::where('guard_name', $guard)->pluck('name')->toArray();

        $toAdd = array_diff($registeredPermissions, $existingPermissions);
        $toRemove = array_diff($existingPermissions, $registeredPermissions);

        foreach ($toAdd as $name) {
            Permission::create(['name' => $name, 'guard_name' => $guard]);
            $this->line("<info>Created:</info> {$name} ({$guard})");
        }

        foreach ($toRemove as $name) {
            Permission::where('name', $name)->delete();
            $this->line("<error>Deleted:</error> {$name}");
        }

        if (empty($toAdd) && empty($toRemove)) {
            $this->info('Permissions are already in sync.');
        } else {
            $cache->flush();
            $this->info('Cleared Authorization Cache.');
        }

        return Command::SUCCESS;
    }
}
