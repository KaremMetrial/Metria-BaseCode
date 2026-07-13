<?php

declare(strict_types=1);

namespace App\Domain\Governance\Services;

use App\Core\Tenancy\TenantManager;
use App\Domain\Governance\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Write an audit entry. Sensitive attributes are masked according to
     * governance.audit.masked_attributes.
     *
     * Pass `$actorId` explicitly when calling from queued jobs — `auth()->id()`
     * returns null outside an HTTP request context, losing traceability.
     * Use the string `'system'` for fully automated actions with no user actor.
     */
    public function log(
        string $action,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        array $context = [],
        ?string $tenantId = null,
        int|string|null $actorId = null,
    ): ?AuditLog {
        if (! config('governance.audit.enabled', true)) {
            return null;
        }

        $resolvedTenantId = $tenantId ?? $auditable?->tenant_id ?? app(TenantManager::class)->id();

        // Resolve actor: explicit > authenticated user > null (not 'unknown', so
        // the column stays null and is distinguishable from a real user ID of 0).
        $resolvedActor = $actorId ?? auth()->id();

        // Guard: request() throws outside HTTP context in some Laravel versions.
        $req = app()->runningInConsole() ? null : request();

        return AuditLog::query()->create([
            'tenant_id' => $resolvedTenantId,
            'user_id' => $resolvedActor,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $this->mask($oldValues),
            'new_values' => $this->mask($newValues),
            'ip_address' => $req?->ip(),
            'user_agent' => mb_substr((string) $req?->userAgent(), 0, 255),
            'context' => $context,
        ]);
    }

    private function mask(array $values): array
    {
        $masked = config('governance.audit.masked_attributes', []);

        foreach ($values as $key => $value) {
            if (in_array($key, $masked, true)) {
                $values[$key] = '********';
            }
        }

        return $values;
    }
}
