<?php

declare(strict_types=1);

namespace App\Domain\Territory\Repositories;

use App\Core\Abstracts\BaseRepository;
use App\Domain\Territory\Models\City;
use Illuminate\Database\Eloquent\Collection;

class CityRepository extends BaseRepository
{
    public function __construct(City $model)
    {
        parent::__construct($model);
    }

    public function getActiveByGovernorate(string $governorateId, ?string $tenantId = null): Collection
    {
        return $this->query($tenantId)
            ->where('governorate_id', $governorateId)
            ->where('is_active', true)
            ->get();
    }
}
