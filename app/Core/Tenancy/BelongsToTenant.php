<?php

declare(strict_types=1);

namespace App\Core\Tenancy;

/**
 * Apply to any model that must be isolated per tenant.
 * Requires a nullable `tenant_id` column.
 */
/** @phpstan-ignore trait.unused */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (! $model instanceof \Illuminate\Database\Eloquent\Model) {
                return;
            }
            $manager = app(TenantManager::class);

            if (config('tenancy.enabled') && $manager->check() && empty($model->getAttribute('tenant_id'))) {
                $model->setAttribute('tenant_id', $manager->id());
            }
        });
    }
}
