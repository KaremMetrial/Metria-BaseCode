<?php

declare(strict_types=1);

namespace Modules\Governance\Infrastructure\Traits;

use Modules\Governance\Infrastructure\Observers\AuditableObserver;

/**
 * Attach to any model whose lifecycle should be written to the audit trail.
 * Optionally define `protected array $auditExclude = [...]` on the model to
 * skip noisy attributes (in addition to globally masked ones).
 */
/** @phpstan-ignore trait.unused */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::observe(AuditableObserver::class);
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
