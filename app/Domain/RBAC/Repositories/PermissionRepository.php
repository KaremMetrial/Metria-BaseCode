<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Repositories;

use App\Core\Abstracts\BaseRepository;
use App\Domain\RBAC\Contracts\PermissionRepositoryInterface;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * @extends BaseRepository<Permission>
 */
class PermissionRepository extends BaseRepository implements PermissionRepositoryInterface
{
    public function __construct(Permission $model)
    {
        parent::__construct($model);
    }

    /**
     * @param array<int, \Illuminate\Contracts\Database\Query\Expression|string> $columns
     * @return \Illuminate\Database\Eloquent\Collection<int, Permission>
     */
    public function all(array $columns = ['*'], ?string $tenantId = null): \Illuminate\Database\Eloquent\Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Permission> $result */
        $result = $this->query($tenantId)->get($columns);

        return $result;
    }

    public function findByName(string $name): Permission
    {
        /** @var Permission $perm */
        $perm = $this->query()->where('name', $name)->firstOrFail();

        return clone $perm;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Permission>
     */
    public function findByNames(array $names): \Illuminate\Database\Eloquent\Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Permission> $result */
        $result = $this->query()->whereIn('name', $names)->get();

        return $result;
    }
}
