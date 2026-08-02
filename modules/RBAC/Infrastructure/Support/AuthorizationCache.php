<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Support;

use Spatie\Permission\PermissionRegistrar;

/**
 * Enterprise Authorization Cache wrapper.
 * Ensures we don't directly couple our business logic to Spatie's cache internals.
 */
class AuthorizationCache
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    /**
     * Flush the entire authorization cache.
     * Should be called after any role/permission mutation.
     */
    public function flush(): void
    {
        $this->registrar->forgetCachedPermissions();
    }

    /**
     * Re-warm the cache immediately (optional).
     */
    public function warm(): void
    {
        $this->registrar->getPermissions();
    }
}
