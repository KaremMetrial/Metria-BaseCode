<?php

declare(strict_types=1);

namespace App\Core\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    public function find(int|string $id): ?Model;

    public function findOrFail(int|string $id): Model;

    public function all(array $columns = ['*']): Collection;

    public function paginate(?int $perPage = null): LengthAwarePaginator;

    public function create(array $attributes): Model;

    public function update(Model $model, array $attributes): Model;

    public function delete(Model $model): bool;
}
