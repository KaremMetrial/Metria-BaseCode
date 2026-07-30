<?php

declare(strict_types=1);

namespace App\Domain\Territory\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDuplicatesCommand extends Command
{
    protected $signature = 'zones:check-duplicates';

    protected $description = 'Scan the zones table for duplicate (tenant_id, code) rows prior to applying composite unique constraint.';

    public function handle(): int
    {
        $this->info('Scanning zones table for duplicate (tenant_id, code) pairs...');

        $duplicates = DB::table('zones')
            ->select('tenant_id', 'code', DB::raw('count(*) as count'))
            ->groupBy('tenant_id', 'code')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No duplicates found. Safe to apply composite unique constraint.');

            return self::SUCCESS;
        }

        $this->error("Found {$duplicates->count()} duplicate (tenant_id, code) pair(s):");
        foreach ($duplicates as $duplicate) {
            if ($duplicate instanceof \stdClass) {
                $tenantIdVal = $duplicate->tenant_id ?? null;
                $tenantId = is_scalar($tenantIdVal) ? (string) $tenantIdVal : 'NULL';
                $codeVal = $duplicate->code ?? '';
                $code = is_scalar($codeVal) ? (string) $codeVal : '';
                $countVal = $duplicate->count ?? 0;
                $count = is_numeric($countVal) ? (int) $countVal : 0;
                $this->line("- tenant_id: {$tenantId}, code: {$code} ({$count} rows)");
            }
        }

        $this->error('Please resolve or clean up these duplicates before running the zones unique migration.');

        return self::FAILURE;
    }
}
