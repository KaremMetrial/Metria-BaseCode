<?php

declare(strict_types=1);

namespace App\Domain\RBAC\Scopes;

use App\Core\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class SystemAwareTenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! config('tenancy.enabled', true)) {
            return;
        }

        $user = Auth::user();
        $tenantId = app(TenantManager::class)->id()
            ?? ($user ? $user->tenant_id : null);

        if ($tenantId === null) {
            return;
        }

        $builder->where(function (Builder $query) use ($tenantId, $model) {
            $table = $model->getTable();
            // Belonging strictly to the current tenant
            $query->where($table.'.tenant_id', $tenantId)
                  // OR belonging to the system (tenant_id = null)
                ->orWhere(function (Builder $subQuery) use ($table) {
                    $subQuery->whereNull($table.'.tenant_id')
                        ->whereHas('metadata', function (Builder $metadataQuery) {
                            $metadataQuery->where('is_system', true);
                        });
                });
        });
    }
}
