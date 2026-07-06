<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Models\IdempotencyKey;
use App\Domain\Governance\Models\AuditLog;
use Illuminate\Console\Command;

class PruneGovernanceData extends Command
{
    protected $signature = 'governance:prune';

    protected $description = 'Prune expired audit logs and idempotency keys according to retention policy';

    public function handle(): int
    {
        $auditDays = (int) config('governance.audit.retention_days', 365);
        $idempotencyHours = (int) config('governance.idempotency.ttl_hours', 24);

        $audits = AuditLog::query()->where('created_at', '<', now()->subDays($auditDays))->delete();
        $keys = IdempotencyKey::query()->where('created_at', '<', now()->subHours($idempotencyHours))->delete();

        $this->info("Pruned {$audits} audit log(s) and {$keys} idempotency key(s).");

        return self::SUCCESS;
    }
}
