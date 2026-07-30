<?php

declare(strict_types=1);

namespace App\Domain\Territory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Domain\Territory\Models\Country */
class CountryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_translations' => $this->getTranslations('name'),
            'iso_code_2' => $this->iso_code_2,
            'iso_code_3' => $this->iso_code_3,
            'phone_code' => $this->phone_code,
            'currency' => $this->currency,
            'is_active' => $this->is_active,
        ];
    }
}
