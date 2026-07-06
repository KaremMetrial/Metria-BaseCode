<?php

declare(strict_types=1);

namespace App\Domain\Territory\Services;

use App\Domain\Territory\Filters\TerritoryFilter;
use App\Domain\Territory\Repositories\GovernorateRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class GovernorateService
{
    private const CACHE_TTL = 86400;

    public function __construct(private readonly GovernorateRepository $repository) {}

    public function getGovernorates(string $countryId, ?TerritoryFilter $filter = null): Collection
    {
        if ($filter !== null) {
            return $this->repository->filter($filter)->where('country_id', $countryId)->get();
        }

        return Cache::remember("territory:governorates:{$countryId}", self::CACHE_TTL, function () use ($countryId) {
            return $this->repository->getActiveByCountry($countryId);
        });
    }

    public function clearCache(string $countryId): void
    {
        Cache::forget("territory:governorates:{$countryId}");
    }
}
