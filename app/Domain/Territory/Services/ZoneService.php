<?php

declare(strict_types=1);

namespace App\Domain\Territory\Services;

use App\Core\Events\EventBus;
use App\Domain\Governance\Services\AuditLogger;
use App\Domain\Territory\Events\ZoneStatusChanged;
use App\Domain\Territory\Filters\TerritoryFilter;
use App\Domain\Territory\Models\Zone;
use App\Domain\Territory\Repositories\ZoneRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ZoneService
{
    public function __construct(
        private readonly ZoneRepository $repository,
        private readonly EventBus $events,
        private readonly AuditLogger $audit,
    ) {}

    public function getZones(?string $cityId = null, ?string $tenantId = null, ?TerritoryFilter $filter = null): Collection
    {
        if ($filter !== null) {
            $query = $this->repository->filter($filter);
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

        return $this->repository->getActiveZones($cityId, $tenantId);
    }

    public function resolveZoneByCoordinates(float $lat, float $lng, ?string $tenantId = null, ?string $cityId = null): ?Zone
    {
        $zones = $this->getZones($cityId, $tenantId);

        foreach ($zones as $zone) {
            if ($zone->containsCoordinate($lat, $lng)) {
                return $zone;
            }
        }

        return null;
    }

    public function createZone(array $data, ?string $tenantId = null): Zone
    {
        return DB::transaction(function () use ($data, $tenantId) {
            /** @var Zone $zone */
            $zone = $this->repository->create(array_merge($data, [
                'tenant_id' => $tenantId ?? $data['tenant_id'] ?? null,
            ]));

            $this->events->publish(new ZoneStatusChanged($zone, 'created'));
            $this->audit->log('zone.created', $zone, ['code' => $zone->code]);

            return $zone;
        });
    }

    public function updateZoneStatus(Zone $zone, bool $isActive, string $reason = 'status_updated'): Zone
    {
        return DB::transaction(function () use ($zone, $isActive, $reason) {
            /** @var Zone $zone */
            $zone = $this->repository->update($zone, ['is_active' => $isActive]);

            $this->events->publish(new ZoneStatusChanged($zone, $reason));
            $this->audit->log('zone.status_updated', $zone, ['is_active' => $isActive, 'reason' => $reason]);

            return $zone;
        });
    }
}
