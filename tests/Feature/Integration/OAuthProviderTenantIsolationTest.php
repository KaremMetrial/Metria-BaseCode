<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use Modules\Auth\Domain\Models\User;
use Modules\Integration\Domain\Models\OAuthProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * OAuthProvider previously had no tenant scoping at all: any user holding
 * the routine per-tenant permission integrations.oauth.manage could
 * read/update/delete another tenant's OAuth client_id/client_secret by
 * UUID. This locks that down at both the query (forTenant scope) and
 * policy (tenant_id match) layers.
 */
class OAuthProviderTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function tenantUser(string $tenantId): User
    {
        DB::table('tenants')->updateOrInsert(
            ['id' => $tenantId],
            ['name' => "Tenant {$tenantId}", 'slug' => "tenant-{$tenantId}", 'active' => 1, 'created_at' => now(), 'updated_at' => now()]
        );

        $user = User::factory()->create(['tenant_id' => $tenantId]);

        // RBAC uses Spatie's tenant-scoped "teams" feature (team_foreign_key
        // = tenant_id) — a permission must be granted under the same team
        // context the request will later resolve, or $user->can(...)
        // evaluates against the wrong team and silently returns false.
        setPermissionsTeamId($tenantId);
        Permission::firstOrCreate(['name' => 'integrations.oauth.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'integrations.oauth.view', 'guard_name' => 'web']);
        $user->givePermissionTo(['integrations.oauth.manage', 'integrations.oauth.view']);

        return $user;
    }

    public function test_user_cannot_view_another_tenants_oauth_provider(): void
    {
        $ownerTenant = $this->tenantUser('tenant-a');
        $intruderTenant = $this->tenantUser('tenant-b');

        Sanctum::actingAs($ownerTenant);
        $provider = OAuthProvider::create([
            'tenant_id' => 'tenant-a',
            'provider' => 'google',
            'client_id' => 'client-a',
            'client_secret' => 'secret-a',
            'redirect_url' => 'https://tenant-a.example.com/callback',
            'is_enabled' => true,
        ]);

        Sanctum::actingAs($intruderTenant);
        $this->getJson("/api/v1/auth/oauth-providers/{$provider->id}")->assertStatus(404);
        $this->putJson("/api/v1/auth/oauth-providers/{$provider->id}", [
            'provider' => 'google',
            'client_id' => 'hijacked',
            'client_secret' => 'hijacked-secret',
            'redirect_url' => 'https://attacker.example.com/callback',
        ])->assertStatus(404);
        $this->deleteJson("/api/v1/auth/oauth-providers/{$provider->id}")->assertStatus(404);

        $this->assertDatabaseHas('oauth_providers', [
            'id' => $provider->id,
            'client_id' => 'client-a',
        ]);
    }

    public function test_user_can_manage_their_own_tenants_oauth_provider(): void
    {
        $user = $this->tenantUser('tenant-a');
        Sanctum::actingAs($user);

        $provider = OAuthProvider::create([
            'tenant_id' => 'tenant-a',
            'provider' => 'google',
            'client_id' => 'client-a',
            'client_secret' => 'secret-a',
            'redirect_url' => 'https://tenant-a.example.com/callback',
            'is_enabled' => true,
        ]);

        $this->getJson("/api/v1/auth/oauth-providers/{$provider->id}")->assertStatus(200);
    }

    public function test_a_global_provider_with_no_tenant_is_visible_to_any_tenant(): void
    {
        $user = $this->tenantUser('tenant-a');

        $global = OAuthProvider::create([
            'tenant_id' => null,
            'provider' => 'github',
            'client_id' => 'shared-client',
            'client_secret' => 'shared-secret',
            'redirect_url' => 'https://example.com/callback',
            'is_enabled' => true,
        ]);

        Sanctum::actingAs($user);
        $this->getJson("/api/v1/auth/oauth-providers/{$global->id}")->assertStatus(200);
    }
}
