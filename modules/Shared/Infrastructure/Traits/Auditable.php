<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Traits;

use Modules\Shared\Domain\Contracts\AuditObserver;

/**
 * Attach to any model whose lifecycle should be written to the audit trail.
 * Optionally define `protected array $auditExclude = [...]` on the model to
 * skip noisy attributes (in addition to globally masked ones).
 *
 * The actual audit-writing observer is owned by the Governance module and
 * resolved here through the AuditObserver contract, so models in any module
 * can opt in without importing Governance directly.
 */
/** @phpstan-ignore trait.unused */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::observe(app(AuditObserver::class));
    }

    public function auditExcluded(): array
    {
        $excludedVal = property_exists($this, 'auditExclude') ? $this->auditExclude : [];
        $excluded = is_array($excludedVal) ? $excludedVal : [];

        return array_merge(
            $excluded,
            ['updated_at', 'created_at', 'remember_token'],
        );
    }
}
