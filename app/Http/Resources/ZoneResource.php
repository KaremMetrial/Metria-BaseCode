<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'city_id' => $this->city_id,
            'name' => $this->name,
            'name_translations' => $this->getTranslations('name'),
            'code' => $this->code,
            'polygon_coordinates' => $this->polygon_coordinates,
            'is_active' => $this->is_active,
        ];
    }
}
