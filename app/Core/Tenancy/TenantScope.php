<?php

declare(strict_types=1);

namespace App\Core\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! config('tenancy.enabled')) {
            return;
        }

        $tenant = app(TenantManager::class);

        if ($tenant->check()) {
            $builder->where($model->getTable().'.tenant_id', $tenant->id());
        }
    }
}
