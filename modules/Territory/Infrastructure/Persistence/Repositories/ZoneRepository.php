<?php

declare(strict_types=1);

namespace Modules\Territory\Infrastructure\Persistence\Repositories;

use Modules\Shared\Infrastructure\Persistence\BaseRepository;
use Modules\Territory\Domain\Models\Zone;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Zone>
 */
class ZoneRepository extends BaseRepository
{
    public function __construct(Zone $model)
    {
        parent::__construct($model);
    }

    /**
     * @return Collection<int, Zone>
     */
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

        /** @var Collection<int, Zone> $results */
        $results = $query->get();

        return $results;
    }
}
