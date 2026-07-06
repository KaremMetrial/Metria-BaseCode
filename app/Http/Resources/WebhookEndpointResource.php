<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookEndpointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'events' => $this->events,
            'active' => $this->active,
            // Shown only when explicitly passed (creation response).
            'secret' => $this->when($this->additional['reveal_secret'] ?? false, $this->secret),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
