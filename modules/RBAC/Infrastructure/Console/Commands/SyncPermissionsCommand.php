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
        $guardOpt = $this->option('guard');
        $guard = is_string($guardOpt) && $guardOpt !== '' ? $guardOpt : config('auth.defaults.guard', 'web');
        $guard = is_string($guard) ? $guard : 'web';

        $this->info("Syncing permissions from PermissionRegistry for guard: {$guard}...");

        $registeredPermissions = array_map(fn ($v) => is_scalar($v) ? (string) $v : '', PermissionRegistry::all());
        $existingPermissions = array_map(fn ($v) => is_scalar($v) ? (string) $v : '', Permission::where('guard_name', $guard)->pluck('name')->toArray());

        $toAdd = array_diff($registeredPermissions, $existingPermissions);
        $toRemove = array_diff($existingPermissions, $registeredPermissions);

        foreach ($toAdd as $name) {
            $nameStr = (string) $name;
            Permission::create(['name' => $nameStr, 'guard_name' => $guard]);
            $this->line("<info>Created:</info> {$nameStr} ({$guard})");
        }

        foreach ($toRemove as $name) {
            $nameStr = (string) $name;
            Permission::where('name', $nameStr)->delete();
            $this->line("<error>Deleted:</error> {$nameStr}");
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
