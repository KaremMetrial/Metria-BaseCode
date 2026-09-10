<?php

declare(strict_types=1);

namespace Modules\Integration\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Integration\Domain\Models\OAuthProvider;

/**
 * @mixin OAuthProvider
 */
class OAuthProviderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'provider' => $this->provider,
            'client_id' => $this->client_id,
            // client_secret is intentionally never exposed, even hashed/masked.
            'redirect_url' => $this->redirect_url,
            'scopes' => $this->scopes,
            'is_enabled' => $this->is_enabled,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
