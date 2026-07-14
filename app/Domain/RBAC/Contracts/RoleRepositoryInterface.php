<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Contracts;

use App\Domain\RBAC\Models\Role;
use Illuminate\Support\Collection;

interface RoleRepositoryInterface
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Role>
     */
    public function all(): \Illuminate\Database\Eloquent\Collection;

    public function findById(string $id): Role;

    public function findByName(string $name): Role;

    public function createWithMetadata(array $attributes, array $metadata = [], ?string $tenantId = null): Role;

    public function updateWithMetadata(Role $role, array $attributes, array $metadata = [], ?string $tenantId = null): Role;

    public function delete(Role $role): bool;
}
