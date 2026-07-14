<?php

declare(strict_types=1);

namespace App\Core\Providers;

use App\Core\Tenancy\TenantManager;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

class QueueTenantProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! config('features.queue_context', true)) {
            return;
        }

        Queue::createPayloadUsing(function ($connection, $queue, $payload) {
            return [
                'tenant_id' => app(TenantManager::class)->id(),
            ];
        });

        Queue::before(function (JobProcessing $event) {
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
        app(TenantManager::class)->set(null);

        if (class_exists(PermissionRegistrar::class)) {
            if (function_exists('setPermissionsTeamId')) {
                setPermissionsTeamId(null);
            }
            $cacheKey = 'spatie.permission.cache.system';
            config(['permission.cache.key' => $cacheKey]);

            $registrar = app(PermissionRegistrar::class);
            $registrar->cacheKey = $cacheKey;
            $registrar->forgetCachedPermissions();
        }
    }
}
