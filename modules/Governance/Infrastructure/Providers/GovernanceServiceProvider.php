<?php

declare(strict_types=1);

namespace Modules\Governance\Infrastructure\Providers;

use Modules\Governance\Infrastructure\Console\Commands\PruneGovernanceData;
use Modules\Governance\Domain\Enums\ApprovalStatus;
use Modules\Governance\Domain\Models\ApprovalRequest;
use Modules\Governance\Domain\Models\AuditLog;
use Modules\Governance\Domain\Models\FeatureFlag;
use Modules\Governance\Domain\Models\Setting;
use Modules\Governance\Presentation\Policies\ApprovalRequestPolicy;
use Modules\Governance\Presentation\Policies\AuditLogPolicy;
use Modules\Governance\Presentation\Policies\FeatureFlagPolicy;
use Modules\Governance\Presentation\Policies\SettingPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Application\Support\EnumRegistry;

class GovernanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/governance.php', 'governance');
    }

    public function boot(): void
    {
        Gate::policy(Setting::class, SettingPolicy::class);
        Gate::policy(FeatureFlag::class, FeatureFlagPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(ApprovalRequest::class, ApprovalRequestPolicy::class);

        EnumRegistry::register('approval_status', ApprovalStatus::class);

        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__.'/../../Presentation/routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneGovernanceData::class,
            ]);
        }
    }
}
