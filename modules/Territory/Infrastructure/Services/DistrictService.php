<?php

declare(strict_types=1);

namespace Modules\Territory\Infrastructure\Services;

use Modules\Territory\Infrastructure\Persistence\Filters\TerritoryFilter;
use Modules\Territory\Infrastructure\Persistence\Repositories\DistrictRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class DistrictService
{
    private const CACHE_TTL = 86400;

    public function __construct(private readonly DistrictRepository $repository) {}

    public function getDistricts(string $cityId, ?TerritoryFilter $filter = null): Collection
    {
        if ($filter !== null) {
            return $this->repository->filter($filter)->where('city_id', $cityId)->get();
        }

        return Cache::remember("territory:districts:{$cityId}", self::CACHE_TTL, function () use ($cityId) {
            return $this->repository->getActiveByCity($cityId);
        });
    }

    public function clearCache(string $cityId): void
    {
        Cache::forget("territory:districts:{$cityId}");
    }
}
