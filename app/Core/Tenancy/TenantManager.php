<?php

declare(strict_types=1);

namespace App\Core\Tenancy;

/**
 * Holds the tenant for the current request/job. Registered as a singleton.
 */
class TenantManager
{
    private int|string|null $tenantId = null;

    public function set(int|string|null $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function id(): int|string|null
    {
        return $this->tenantId;
    }

    public function check(): bool
    {
        return $this->tenantId !== null;
    }
}
