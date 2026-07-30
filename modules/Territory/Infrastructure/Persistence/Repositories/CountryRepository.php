<?php

declare(strict_types=1);

namespace Modules\Territory\Infrastructure\Persistence\Repositories;

use Modules\Shared\Infrastructure\Persistence\BaseRepository;
use Modules\Territory\Domain\Models\Country;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Country>
 */
class CountryRepository extends BaseRepository
{
    public function __construct(Country $model)
    {
        parent::__construct($model);
    }

    public function getActiveCountries(?string $tenantId = null): Collection
    {
        return $this->query($tenantId)
            ->where('is_active', true)
            ->orderBy('iso_code_2')
            ->get();
    }
}
