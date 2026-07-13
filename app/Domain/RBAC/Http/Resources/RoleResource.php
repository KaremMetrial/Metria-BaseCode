<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tenant_id' => $this->tenant_id,
            'display_name' => $this->metadata ? $this->metadata->getTranslations('display_name') : ['en' => $this->name],
            'description' => $this->metadata ? $this->metadata->getTranslations('description') : null,
            'is_system' => $this->metadata?->is_system ?? false,
            'is_editable' => $this->metadata?->is_editable ?? true,
            'is_assignable' => $this->metadata?->is_assignable ?? true,
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
