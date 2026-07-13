<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\RBAC\Models\Role;
use Illuminate\Support\Str;

trait CreatesRole
{
    /**
     * Create a mock role with metadata.
     */
    protected function createRole(?string $tenantId = null, array $attributes = [], array $metadata = []): Role
    {
        /** @var Role $role */
        $attributes['name'] = $attributes['name'] ?? 'role_' . Str::random(8);
        $attributes['guard_name'] = $attributes['guard_name'] ?? 'web';
        
        $role = Role::firstOrCreate([
            'name' => $attributes['name'],
            'guard_name' => $attributes['guard_name'],
            'tenant_id' => $tenantId,
        ]);

        if (!$role->metadata) {
            $role->metadata()->create(array_merge([
                'display_name' => ['en' => 'Mock Role', 'ar' => 'دور وهمي'],
                'description' => ['en' => 'A mock role for testing', 'ar' => 'دور وهمي للاختبار'],
                'priority' => 100,
                'is_system' => false,
                'is_editable' => true,
                'is_assignable' => true,
            ], $metadata));
        }

        return $role->load('metadata');
    }

    protected function createSystemRole(string $name, int $priority = 10): Role
    {
        return $this->createRole(null, ['name' => $name], [
            'is_system' => true,
            'is_editable' => false,
            'priority' => $priority,
        ]);
    }
}
