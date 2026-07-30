<?php

declare(strict_types=1);

namespace App\Domain\Territory\Services;

use App\Domain\Territory\Filters\TerritoryFilter;
use App\Domain\Territory\Repositories\CountryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class CountryService
{
    private const CACHE_TTL = 86400;

    public function __construct(private readonly CountryRepository $repository) {}

    public function getCountries(?TerritoryFilter $filter = null): Collection
    {
        if ($filter !== null) {
            return $this->repository->filter($filter)->get();
        }

        return Cache::remember('territory:countries', self::CACHE_TTL, function () {
            return $this->repository->getActiveCountries();
        });
    }

    public function clearCache(): void
    {
        Cache::forget('territory:countries');
    }
}
