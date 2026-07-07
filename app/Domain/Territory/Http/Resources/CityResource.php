<?php

declare(strict_types=1);

namespace App\Domain\Territory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'governorate_id' => $this->governorate_id,
            'name' => $this->name,
            'name_translations' => $this->getTranslations('name'),
            'postal_code' => $this->postal_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_active' => $this->is_active,
        ];
    }
}
