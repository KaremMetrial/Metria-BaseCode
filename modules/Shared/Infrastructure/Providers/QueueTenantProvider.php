<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Providers;

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Modules\Shared\Infrastructure\Tenancy\TenantManager;
use Spatie\Permission\PermissionRegistrar;

class QueueTenantProvider extends ServiceProvider
{
    /** @var array<int, int|string|null> */
    private array $previousTenantIds = [];

    public function boot(): void
    {
        if (! config('core.queue_context_enabled', true)) {
            return;
        }

        Queue::createPayloadUsing(function ($connection, $queue, $payload) {
            return [
                'tenant_id' => app(TenantManager::class)->id(),
            ];
        });

        Queue::before(function (JobProcessing $event) {
            $this->previousTenantIds[] = app(TenantManager::class)->id();

            $payload = $event->job->payload();
            $tenantIdVal = $payload['tenant_id'] ?? null;
            $tenantId = (is_string($tenantIdVal) || is_int($tenantIdVal)) ? $tenantIdVal : null;

            app(TenantManager::class)->set($tenantId);

            if (class_exists(PermissionRegistrar::class)) {
                if (function_exists('setPermissionsTeamId')) {
                    setPermissionsTeamId($tenantId);
                }
                $cacheKey = 'spatie.permission.cache.'.($tenantId ?? 'system');
                config(['permission.cache.key' => $cacheKey]);

                $registrar = app(PermissionRegistrar::class);
                $registrar->cacheKey = $cacheKey;
                $registrar->forgetCachedPermissions();
            }
        });

        Queue::after(function (JobProcessed $event) {
            $this->resetTenantContext();
        });

        Queue::failing(function () {
            $this->resetTenantContext();
        });
    }

    private function resetTenantContext(): void
    {
        $tenantId = array_pop($this->previousTenantIds);
        app(TenantManager::class)->set($tenantId);

        if (class_exists(PermissionRegistrar::class)) {
            if (function_exists('setPermissionsTeamId')) {
                setPermissionsTeamId($tenantId);
            }
            $cacheKey = 'spatie.permission.cache.'.($tenantId ?? 'system');
            config(['permission.cache.key' => $cacheKey]);

            $registrar = app(PermissionRegistrar::class);
            $registrar->cacheKey = $cacheKey;
            $registrar->forgetCachedPermissions();
        }
    }
}
