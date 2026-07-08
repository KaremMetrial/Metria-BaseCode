<?php

declare(strict_types=1);

namespace App\Domain\Auth\Contracts;

use App\Domain\Auth\Models\User;

interface AuthStrategyInterface
{
    /**
     * Authenticate or resolve a user based on the provided credentials or payload.
     *
     * @param array<string, mixed> $credentials
     */
    public function authenticate(array $credentials, ?string $tenantId = null): User;
}
