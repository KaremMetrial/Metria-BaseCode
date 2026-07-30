<?php

declare(strict_types=1);

namespace App\Domain\Territory\Services;

use App\Domain\Territory\Filters\TerritoryFilter;
use App\Domain\Territory\Repositories\CityRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class CityService
{
    private const CACHE_TTL = 86400;

    public function __construct(private readonly CityRepository $repository) {}

    public function getCities(string $governorateId, ?TerritoryFilter $filter = null): Collection
    {
        if ($filter !== null) {
            return $this->repository->filter($filter)->where('governorate_id', $governorateId)->get();
        }

        return Cache::remember("territory:cities:{$governorateId}", self::CACHE_TTL, function () use ($governorateId) {
            return $this->repository->getActiveByGovernorate($governorateId);
        });
    }

    public function clearCache(string $governorateId): void
    {
        Cache::forget("territory:cities:{$governorateId}");
    }
}
