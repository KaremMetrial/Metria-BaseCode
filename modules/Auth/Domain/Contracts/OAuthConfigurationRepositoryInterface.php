<?php

declare(strict_types=1);

namespace App\Domain\Auth\Contracts;

interface OAuthConfigurationRepositoryInterface
{
    /**
     * Retrieve the configuration array for a specific OAuth provider and optional tenant.
     *
     * @return array{client_id: string, client_secret: string, redirect: string}|null
     */
    public function getProviderConfig(string $provider, ?string $tenantId = null): ?array;

    /**
     * Determine if a specific OAuth provider is enabled for the system or tenant.
     */
    public function isProviderEnabled(string $provider, ?string $tenantId = null): bool;
}
