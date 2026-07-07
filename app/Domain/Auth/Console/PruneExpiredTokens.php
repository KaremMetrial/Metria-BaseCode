<?php

declare(strict_types=1);

namespace App\Domain\Auth\Console;

use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class PruneExpiredTokens extends Command
{
    protected $signature = 'auth:prune-tokens';

    protected $description = 'Prune personal access tokens that have expired or been inactive';

    public function handle(): void
    {
        $cutoff = now()->subDays(30);

        $count = PersonalAccessToken::query()
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('last_used_at')
                    ->where('created_at', '<', $cutoff);
            })
            ->orWhere('last_used_at', '<', $cutoff)
            ->delete();

        $this->info("Successfully pruned {$count} inactive Sanctum tokens.");
    }
}
