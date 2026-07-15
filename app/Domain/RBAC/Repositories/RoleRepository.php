<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Repositories;

use App\Core\Abstracts\BaseRepository;
use App\Domain\RBAC\Contracts\RoleRepositoryInterface;
use App\Domain\RBAC\Models\Role;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends BaseRepository<Role>
 */
class RoleRepository extends BaseRepository implements RoleRepositoryInterface
{
    public function __construct(Role $model)
    {
        parent::__construct($model);
    }

    /**
     * @param array<int, \Illuminate\Contracts\Database\Query\Expression|string> $columns
     * @return \Illuminate\Database\Eloquent\Collection<int, Role>
     */
    public function all(array $columns = ['*'], ?string $tenantId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->model->with(['metadata', 'permissions']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Role> $result */
        $result = $query->get($columns);

        return $result;
    }

    public function findById(string $id): Role
    {
        /** @var Role $role */
        $role = $this->query()->with('metadata')->findOrFail($id);

        return $role;
    }

    public function findByName(string $name): Role
    {
        /** @var Role $role */
        $role = $this->query()->with('metadata')->where('name', $name)->firstOrFail();

        return $role;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $metadata
     */
    public function createWithMetadata(array $attributes, array $metadata = [], ?string $tenantId = null): Role
    {
        /** @var Role $role */
        $role = parent::create($attributes, $tenantId);

        if (! empty($metadata)) {
            $role->metadata()->create($metadata);
        }

        return $role->load('metadata');
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $metadata
     */
    public function updateWithMetadata(Role $role, array $attributes, array $metadata = [], ?string $tenantId = null): Role
    {
        if (! empty($attributes)) {
            parent::update($role, $attributes, $tenantId);
        }

        if (! empty($metadata)) {
            $role->metadata()->updateOrCreate(['role_id' => $role->id], $metadata);
        }

        /** @var Role $refreshed */
        $refreshed = $role->refresh()->load('metadata');

        return $refreshed;
    }

    /**
     * @param  Role  $role
     */
    public function delete(Model $role, ?string $tenantId = null): bool
    {
        return parent::delete($role, $tenantId);
    }
}
