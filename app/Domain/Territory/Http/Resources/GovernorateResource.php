<?php

declare(strict_types=1);

namespace App\Domain\Territory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GovernorateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_id' => $this->country_id,
            'name' => $this->name,
            'name_translations' => $this->getTranslations('name'),
            'code' => $this->code,
            'is_active' => $this->is_active,
        ];
    }
}
