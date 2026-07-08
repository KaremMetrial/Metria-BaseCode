<?php

declare(strict_types=1);

namespace App\Domain\Governance\Providers;

use App\Domain\Governance\Console\Commands\PruneGovernanceData;
use App\Domain\Governance\Models\ApprovalRequest;
use App\Domain\Governance\Models\AuditLog;
use App\Domain\Governance\Models\FeatureFlag;
use App\Domain\Governance\Models\Setting;
use App\Domain\Governance\Policies\ApprovalRequestPolicy;
use App\Domain\Governance\Policies\AuditLogPolicy;
use App\Domain\Governance\Policies\FeatureFlagPolicy;
use App\Domain\Governance\Policies\SettingPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class GovernanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Setting::class, SettingPolicy::class);
        Gate::policy(FeatureFlag::class, FeatureFlagPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(ApprovalRequest::class, ApprovalRequestPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneGovernanceData::class,
            ]);
        }
    }
}
