<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Repositories;

use App\Core\Abstracts\BaseRepository;
use App\Domain\RBAC\Contracts\PermissionRepositoryInterface;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

class PermissionRepository extends BaseRepository implements PermissionRepositoryInterface
{
    public function __construct(Permission $model)
    {
        parent::__construct($model);
    }

    public function all(array $columns = ['*'], ?string $tenantId = null): \Illuminate\Database\Eloquent\Collection
    {
        return $this->query($tenantId)->get($columns);
    }

    public function findByName(string $name): Permission
    {
        return clone $this->query()->where('name', $name)->firstOrFail();
    }

    public function findByNames(array $names): Collection
    {
        return $this->query()->whereIn('name', $names)->get();
    }
}
