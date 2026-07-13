<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

trait CreatesPermission
{
    /**
     * Create a mock permission.
     */
    protected function createPermission(array $attributes = []): Permission
    {
        $attributes['name'] = $attributes['name'] ?? 'permission.'.Str::random(8);
        $attributes['guard_name'] = $attributes['guard_name'] ?? 'web';

        return Permission::firstOrCreate([
            'name' => $attributes['name'],
            'guard_name' => $attributes['guard_name'],
        ]);
    }
}
