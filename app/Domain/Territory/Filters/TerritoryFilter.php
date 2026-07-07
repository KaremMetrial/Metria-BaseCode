<?php

declare(strict_types=1);

namespace App\Domain\Territory\Filters;

use App\Core\Support\Filters\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

class TerritoryFilter extends QueryFilter
{
    /** @var array<int, string> */
    protected array $allowedFilters = [
        'is_active',
        'country_id',
        'governorate_id',
        'city_id',
        'code',
        'iso_code_2',
        'bounds',
    ];

    /** @var array<int, string> */
    protected array $allowedSorts = [
        'created_at',
        'name',
        'code',
        'iso_code_2',
    ];

    public function search(string $term): void
    {
        $model = $this->builder->getModel();
        $fillable = $model->getFillable();

        $this->builder->where(function (Builder $query) use ($term, $fillable) {
            // Search across the entire JSON translatable column (matching ANY language: en, ar, fr, de, es, tr, etc.)
            $query->where('name', 'LIKE', "%{$term}%");

            if (in_array('code', $fillable, true)) {
                $query->orWhere('code', 'LIKE', "%{$term}%");
            }
            if (in_array('iso_code_2', $fillable, true)) {
                $query->orWhere('iso_code_2', 'LIKE', "%{$term}%");
            }
            if (in_array('iso_code_3', $fillable, true)) {
                $query->orWhere('iso_code_3', 'LIKE', "%{$term}%");
            }
            if (in_array('postal_code', $fillable, true)) {
                $query->orWhere('postal_code', 'LIKE', "%{$term}%");
            }
        });
    }

    public function isActive(string|bool $status): void
    {
        $this->builder->where('is_active', filter_var($status, FILTER_VALIDATE_BOOLEAN));
    }

    public function countryId(string $id): void
    {
        $this->builder->where('country_id', $id);
    }

    public function governorateId(string $id): void
    {
        $this->builder->where('governorate_id', $id);
    }

    public function cityId(string $id): void
    {
        $this->builder->where('city_id', $id);
    }

    public function code(string $code): void
    {
        $this->builder->where('code', $code);
    }

    public function isoCode2(string $code): void
    {
        $this->builder->where('iso_code_2', strtoupper($code));
    }

    /**
     * Filter entities by geospatial bounding box: ?filter[bounds]=latMin,lngMin,latMax,lngMax
     */
    public function bounds(string $coordinates): void
    {
        $parts = explode(',', $coordinates);
        if (count($parts) !== 4) {
            return;
        }

        [$latMin, $lngMin, $latMax, $lngMax] = array_map('floatval', $parts);

        $this->builder->whereBetween('latitude', [min($latMin, $latMax), max($latMin, $latMax)])
            ->whereBetween('longitude', [min($lngMin, $lngMax), max($lngMin, $lngMax)]);
    }
}
