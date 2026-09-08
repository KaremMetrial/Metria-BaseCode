<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Writes an entry to the platform's audit trail. Implemented by the
 * Governance module (AuditLogger); other modules depend on this contract
 * instead of importing Governance's concrete service directly.
 */
interface AuditRecorder
{
    /**
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     * @param array<string, mixed> $context
     */
    public function log(
        string $action,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        array $context = [],
        ?string $tenantId = null,
        int|string|null $actorId = null,
    ): ?Model;
}
