<?php

declare(strict_types=1);

namespace App\Domain\Governance\Providers;

use App\Domain\Governance\Console\Commands\PruneGovernanceData;
use Illuminate\Support\ServiceProvider;

class GovernanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneGovernanceData::class,
            ]);
        }
    }
}
