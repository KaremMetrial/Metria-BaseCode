<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Traits;

use Illuminate\Database\Eloquent\Builder;
use Modules\Shared\Domain\Specifications\QueryFilter;

/**
 * Trait enabling Eloquent Models or Repositories to apply QueryFilter specifications.
 * @phpstan-ignore trait.unused
 */
trait Filterable
{
    public function scopeFilter(Builder $query, QueryFilter $filter): Builder
    {
        return $filter->apply($query);
    }
}
