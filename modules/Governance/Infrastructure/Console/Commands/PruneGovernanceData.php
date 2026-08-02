<?php

declare(strict_types=1);

namespace Modules\Governance\Infrastructure\Console\Commands;

use Modules\Shared\Infrastructure\Persistence\Models\IdempotencyKey;
use Modules\Governance\Domain\Models\AuditLog;
use Illuminate\Console\Command;

class PruneGovernanceData extends Command
{
    protected $signature = 'governance:prune';

    protected $description = 'Prune expired audit logs and idempotency keys according to retention policy';

    public function handle(): int
    {
        $auditDaysVal = config('governance.audit.retention_days', 365);
        $auditDays = is_numeric($auditDaysVal) ? (int) $auditDaysVal : 365;

        $idempotencyHoursVal = config('core.idempotency.ttl_hours', 24);
        $idempotencyHours = is_numeric($idempotencyHoursVal) ? (int) $idempotencyHoursVal : 24;

        $auditsVal = AuditLog::query()->where('created_at', '<', now()->subDays($auditDays))->delete();
        $audits = is_numeric($auditsVal) ? (int) $auditsVal : 0;
        $keysVal = IdempotencyKey::query()->where('created_at', '<', now()->subHours($idempotencyHours))->delete();
        $keys = is_numeric($keysVal) ? (int) $keysVal : 0;

        $this->info("Pruned {$audits} audit log(s) and {$keys} idempotency key(s).");

        return self::SUCCESS;
    }
}
