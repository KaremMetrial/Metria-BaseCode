<?php

declare(strict_types=1);

namespace App\Core\Abstracts;

use App\Core\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Thin Eloquent repository. Use repositories to keep complex query logic
 * out of controllers/services — not to hide Eloquent from yourself.
 */
abstract class BaseRepository implements RepositoryInterface
{
    public function __construct(protected Model $model) {}

    public function query(?string $tenantId = null): Builder
    {
        $query = $this->model->newQuery();

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }

    public function find(int|string $id, ?string $tenantId = null): ?Model
    {
        return $this->query($tenantId)->find($id);
    }

    public function findOrFail(int|string $id, ?string $tenantId = null): Model
    {
        return $this->query($tenantId)->findOrFail($id);
    }

    /**
     * @param array<int, \Illuminate\Contracts\Database\Query\Expression|string> $columns
     * @return Collection<int, Model>
     */
    public function all(array $columns = ['*'], ?string $tenantId = null): Collection
    {
        return $this->query($tenantId)->get($columns);
    }

    public function paginate(?int $perPage = null, ?string $tenantId = null): LengthAwarePaginator
    {
        $configPerPage = config('core.api.per_page', 20);
        $configMaxPerPage = config('core.api.max_per_page', 100);
        $perPage = min(
            $perPage ?? (is_numeric($configPerPage) ? (int) $configPerPage : 20),
            is_numeric($configMaxPerPage) ? (int) $configMaxPerPage : 100,
        );

        return $this->query($tenantId)->latest()->paginate($perPage);
    }

    public function filter(QueryFilter $filter, ?string $tenantId = null): Builder
    {
        return $filter->apply($this->query($tenantId));
    }

    public function getFiltered(QueryFilter $filter, ?int $perPage = null, ?string $tenantId = null): LengthAwarePaginator
    {
        $configPerPage = config('core.api.per_page', 20);
        $configMaxPerPage = config('core.api.max_per_page', 100);
        $perPage = min(
            $perPage ?? (is_numeric($configPerPage) ? (int) $configPerPage : 20),
            is_numeric($configMaxPerPage) ? (int) $configMaxPerPage : 100,
        );

        return $this->filter($filter, $tenantId)->paginate($perPage);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes, ?string $tenantId = null): Model
    {
        if ($tenantId !== null && ! isset($attributes['tenant_id'])) {
            $attributes['tenant_id'] = $tenantId;
        }

        return $this->query($tenantId)->create($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(Model $model, array $attributes, ?string $tenantId = null): Model
    {
        $model->fill($attributes)->save();

        return $model->refresh();
    }

    public function delete(Model $model, ?string $tenantId = null): bool
    {
        return (bool) $model->delete();
    }
}
