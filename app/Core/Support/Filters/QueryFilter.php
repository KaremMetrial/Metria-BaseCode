<?php

declare(strict_types=1);

namespace App\Core\Support\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Abstract QueryFilter specification engine.
 * Maps API query parameters (?filter[field]=val&sort=-created_at&search=query)
 * to eloquent query builder constraints cleanly and securely.
 */
abstract class QueryFilter
{
    protected Builder $builder;

    /** @var array<int, string> */
    protected array $allowedFilters = [];

    /** @var array<int, string> */
    protected array $allowedSorts = [];

    protected string $defaultSort = '-created_at';

    public function __construct(protected Request $request) {}

    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        $this->applyFilters();
        $this->applySorting();

        return $this->builder;
    }

    protected function applyFilters(): void
    {
        $filters = $this->request->query('filter', []);
        if (! is_array($filters)) {
            return;
        }

        foreach ($filters as $name => $value) {
            $method = Str::camel((string) $name);

            if ($this->isFilterAllowed((string) $name) && method_exists($this, $method)) {
                if ($value !== null && $value !== '') {
                    $this->{$method}($value);
                }
            }
        }

        $search = $this->request->query('search');
        if (is_string($search) && $search !== '' && method_exists($this, 'search')) {
            $this->search($search);
        }
    }

    protected function applySorting(): void
    {
        $sortQuery = $this->request->query('sort', $this->defaultSort);
        if (! is_string($sortQuery) || $sortQuery === '') {
            return;
        }

        $sorts = explode(',', $sortQuery);
        foreach ($sorts as $sort) {
            $sort = trim($sort);
            if ($sort === '') {
                continue;
            }

            $direction = 'asc';
            if (str_starts_with($sort, '-')) {
                $direction = 'desc';
                $sort = substr($sort, 1);
            }

            if ($this->isSortAllowed($sort)) {
                $this->builder->orderBy($sort, $direction);
            }
        }
    }

    protected function isFilterAllowed(string $filter): bool
    {
        return empty($this->allowedFilters) || in_array($filter, $this->allowedFilters, true);
    }

    protected function isSortAllowed(string $sort): bool
    {
        return empty($this->allowedSorts) || in_array($sort, $this->allowedSorts, true);
    }
}
