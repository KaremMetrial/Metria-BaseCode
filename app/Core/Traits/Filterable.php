<?php

declare(strict_types=1);

namespace App\Core\Traits;

use App\Core\Abstracts\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait enabling Eloquent Models or Repositories to apply QueryFilter specifications.
 */
trait Filterable
{
    public function scopeFilter(Builder $query, QueryFilter $filter): Builder
    {
        return $filter->apply($query);
    }
}
