<?php

declare(strict_types=1);

namespace App\Core\Contracts;

use App\Core\Abstracts\QueryFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    public function find(int|string $id, ?string $tenantId = null): ?Model;

    public function findOrFail(int|string $id, ?string $tenantId = null): Model;

    public function all(array $columns = ['*'], ?string $tenantId = null): Collection;

    public function paginate(?int $perPage = null, ?string $tenantId = null): LengthAwarePaginator;

    public function filter(QueryFilter $filter, ?string $tenantId = null): Builder;

    public function getFiltered(QueryFilter $filter, ?int $perPage = null, ?string $tenantId = null): LengthAwarePaginator;

    public function create(array $attributes, ?string $tenantId = null): Model;

    public function update(Model $model, array $attributes, ?string $tenantId = null): Model;

    public function delete(Model $model, ?string $tenantId = null): bool;
}
