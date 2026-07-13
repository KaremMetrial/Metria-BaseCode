<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Repositories;

use App\Core\Abstracts\BaseRepository;
use App\Domain\RBAC\Contracts\RoleRepositoryInterface;
use App\Domain\RBAC\Models\Role;
use Illuminate\Support\Collection;

class RoleRepository extends BaseRepository implements RoleRepositoryInterface
{
    public function __construct(Role $model)
    {
        parent::__construct($model);
    }

    public function all(array $columns = ['*'], ?string $tenantId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->model->with(['metadata', 'permissions']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get($columns);
    }

    public function findById(string $id): Role
    {
        return $this->query()->with('metadata')->findOrFail($id);
    }

    public function findByName(string $name): Role
    {
        return $this->query()->with('metadata')->where('name', $name)->firstOrFail();
    }

    public function createWithMetadata(array $attributes, array $metadata = [], ?string $tenantId = null): Role
    {
        /** @var Role $role */
        $role = parent::create($attributes, $tenantId);

        if (! empty($metadata)) {
            $role->metadata()->create($metadata);
        }

        return $role->load('metadata');
    }

    public function updateWithMetadata(Role|\Illuminate\Database\Eloquent\Model $role, array $attributes, array $metadata = [], ?string $tenantId = null): Role
    {
        if (! empty($attributes)) {
            parent::update($role, $attributes, $tenantId);
        }

        if (! empty($metadata)) {
            $role->metadata()->updateOrCreate(['role_id' => $role->id], $metadata);
        }

        return $role->refresh()->load('metadata');
    }

    public function delete(Role|\Illuminate\Database\Eloquent\Model $role, ?string $tenantId = null): bool
    {
        return parent::delete($role, $tenantId);
    }
}
