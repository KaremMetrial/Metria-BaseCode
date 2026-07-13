<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Tenancy\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait CreatesTenant
{
    /**
     * Set the current tenant for the application.
     */
    protected function setTenant(string $tenantId): void
    {
        app(TenantManager::class)->set($tenantId);
    }

    /**
     * Generate a random tenant ID and set it as the active tenant.
     */
    protected function setRandomTenant(): string
    {
        $tenantId = (string) Str::uuid();

        // Ensure the tenant exists in the database to satisfy FK constraints
        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'Test Tenant '.Str::random(5),
            'slug' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->setTenant($tenantId);

        return $tenantId;
    }
}
