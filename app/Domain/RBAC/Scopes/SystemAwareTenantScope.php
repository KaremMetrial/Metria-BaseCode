<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class SystemAwareTenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = Auth::check() ? Auth::user()->tenant_id : null;

        if ($tenantId === null) {
            return;
        }

        $builder->where(function (Builder $query) use ($tenantId) {
            // Belonging strictly to the current tenant
            $query->where('roles.tenant_id', $tenantId)
                  // OR belonging to the system (tenant_id = null)
                  ->orWhere(function (Builder $subQuery) {
                      $subQuery->whereNull('roles.tenant_id')
                               ->whereHas('metadata', function (Builder $metadataQuery) {
                                   $metadataQuery->where('is_system', true);
                               });
                  });
        });
    }
}
