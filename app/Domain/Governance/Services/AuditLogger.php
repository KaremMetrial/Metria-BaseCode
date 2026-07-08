<?php

declare(strict_types=1);

namespace App\Domain\Governance\Services;

use App\Domain\Governance\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Write an audit entry. Sensitive attributes are masked according to
     * governance.audit.masked_attributes.
     */
    public function log(
        string $action,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        array $context = [],
        ?string $tenantId = null,
    ): ?AuditLog {
        if (! config('governance.audit.enabled', true)) {
            return null;
        }

        $resolvedTenantId = $tenantId ?? $auditable?->tenant_id ?? app(\App\Core\Tenancy\TenantManager::class)->id();

        return AuditLog::query()->create([
            'tenant_id' => $resolvedTenantId,
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $this->mask($oldValues),
            'new_values' => $this->mask($newValues),
            'ip_address' => request()?->ip(),
            'user_agent' => mb_substr((string) request()?->userAgent(), 0, 255),
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
