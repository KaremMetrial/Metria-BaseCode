<?php

declare(strict_types=1);

namespace App\Domain\Territory\Repositories;

use App\Core\Abstracts\BaseRepository;
use App\Domain\Territory\Models\Zone;
use Illuminate\Database\Eloquent\Collection;

class ZoneRepository extends BaseRepository
{
    public function __construct(Zone $model)
    {
        parent::__construct($model);
    }

    public function getActiveZones(?string $cityId = null, ?string $tenantId = null): Collection
    {
        $query = $this->query()->where('is_active', true);

        if ($cityId !== null) {
            $query->where('city_id', $cityId);
        }

        if ($tenantId !== null) {
            $query->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhereNull('tenant_id');
            });
        }

        return $query->get();
    }
}
