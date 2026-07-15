<?php

declare(strict_types=1);

namespace App\Domain\Territory\Repositories;

use App\Core\Abstracts\BaseRepository;
use App\Domain\Territory\Models\District;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<District>
 */
class DistrictRepository extends BaseRepository
{
    public function __construct(District $model)
    {
        parent::__construct($model);
    }

    public function getActiveByCity(string $cityId, ?string $tenantId = null): Collection
    {
        return $this->query($tenantId)
            ->where('city_id', $cityId)
            ->where('is_active', true)
            ->get();
    }
}
