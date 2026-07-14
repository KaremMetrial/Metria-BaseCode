<?php

declare(strict_types=1);

namespace App\Domain\Territory\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Territory\Filters\TerritoryFilter;
use App\Domain\Territory\Http\Resources\CityResource;
use App\Domain\Territory\Http\Resources\CountryResource;
use App\Domain\Territory\Http\Resources\DistrictResource;
use App\Domain\Territory\Http\Resources\GovernorateResource;
use App\Domain\Territory\Http\Resources\ZoneResource;
use App\Domain\Territory\Services\CityService;
use App\Domain\Territory\Services\CountryService;
use App\Domain\Territory\Services\DistrictService;
use App\Domain\Territory\Services\GovernorateService;
use App\Domain\Territory\Services\ZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerritoryController extends ApiController
{
    public function countries(CountryService $service, TerritoryFilter $filter): JsonResponse
    {
        return $this->respond(CountryResource::collection($service->getCountries($filter)));
    }

    public function governorates(string $country, GovernorateService $service, TerritoryFilter $filter): JsonResponse
    {
        return $this->respond(GovernorateResource::collection($service->getGovernorates($country, $filter)));
    }

    public function cities(string $governorate, CityService $service, TerritoryFilter $filter): JsonResponse
    {
        return $this->respond(CityResource::collection($service->getCities($governorate, $filter)));
    }

    public function districts(string $city, DistrictService $service, TerritoryFilter $filter): JsonResponse
    {
        return $this->respond(DistrictResource::collection($service->getDistricts($city, $filter)));
    }

    public function zones(Request $request, ZoneService $service, TerritoryFilter $filter): JsonResponse
    {
        $cityId = $request->query('city_id');
        $tenantId = $request->user()?->tenant_id;

        return $this->respond(ZoneResource::collection($service->getZones(is_string($cityId) ? $cityId : null, $tenantId, $filter)));
    }

    public function resolveZone(Request $request, ZoneService $service): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'city_id' => ['nullable', 'uuid'],
        ]);
        $validatedArray = is_array($validated) ? $validated : [];

        $tenantId = $request->user()?->tenant_id;

        $cityIdVal = $validatedArray['city_id'] ?? null;
        $cityId = is_string($cityIdVal) ? $cityIdVal : null;

        $latVal = $validatedArray['latitude'] ?? 0.0;
        $latitude = is_numeric($latVal) ? (float) $latVal : 0.0;

        $lngVal = $validatedArray['longitude'] ?? 0.0;
        $longitude = is_numeric($lngVal) ? (float) $lngVal : 0.0;

        $zone = $service->resolveZoneByCoordinates(
            $latitude,
            $longitude,
            $tenantId,
            $cityId
        );

        if ($zone === null) {
            return $this->respondError('No operational zone found for given coordinates.', 404, 'zone_not_found');
        }

        return $this->respond(new ZoneResource($zone));
    }
}
