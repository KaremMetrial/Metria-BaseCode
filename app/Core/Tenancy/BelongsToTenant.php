<?php

declare(strict_types=1);

namespace App\Core\Tenancy;

/**
 * Apply to any model that must be isolated per tenant.
 * Requires a nullable `tenant_id` column.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $manager = app(TenantManager::class);

            if (config('tenancy.enabled') && $manager->check() && empty($model->tenant_id)) {
                $model->tenant_id = $manager->id();
            }
        });
    }
}
