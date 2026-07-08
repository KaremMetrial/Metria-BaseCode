<?php

declare(strict_types=1);

namespace App\Domain\Integration\Repositories;

use App\Domain\Auth\Contracts\OAuthConfigurationRepositoryInterface;
use App\Domain\Integration\Models\OAuthProvider;

class DatabaseOAuthConfigurationRepository implements OAuthConfigurationRepositoryInterface
{
    public function getProviderConfig(string $provider, ?string $tenantId = null): ?array
    {
        /** @var OAuthProvider|null $record */
        $record = OAuthProvider::query()
            ->where('provider', $provider)
            ->forTenant($tenantId)
            ->where('is_enabled', true)
            ->orderByRaw('tenant_id IS NOT NULL DESC')
            ->first();

        if (! $record) {
            return null;
        }

        return [
            'client_id' => $record->client_id,
            'client_secret' => $record->client_secret,
            'redirect' => $record->redirect_url,
        ];
    }

    public function isProviderEnabled(string $provider, ?string $tenantId = null): bool
    {
        return OAuthProvider::query()
            ->where('provider', $provider)
            ->forTenant($tenantId)
            ->where('is_enabled', true)
            ->exists();
    }
}
