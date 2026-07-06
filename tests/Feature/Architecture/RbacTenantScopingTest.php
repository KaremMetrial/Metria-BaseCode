<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Domain\Auth\Actions\IssueApiToken;
use App\Domain\Auth\Models\User;
use App\Domain\Governance\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RbacTenantScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_user_receives_sanctum_token_with_granular_role_abilities(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $permission = Permission::firstOrCreate(['name' => 'payments.refund', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'finance-officer', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $action = app(IssueApiToken::class);
        ['token' => $token] = $action($user->email, 'password123', 'mobile-device');

        $this->assertNotEmpty($token);
        $this->assertTrue($user->tokens()->first()->can('payments.refund'));
        $this->assertFalse($user->tokens()->first()->can('admin.super'));
    }

    public function test_super_admin_receives_wildcard_sanctum_token_ability(): void
    {
        $admin = User::factory()->create(['password' => bcrypt('password123')]);

        $permission = Permission::firstOrCreate(['name' => 'admin.super', 'guard_name' => 'web']);
        $admin->givePermissionTo($permission);

        $action = app(IssueApiToken::class);
        ['token' => $token] = $action($admin->email, 'password123', 'admin-dashboard');

        $this->assertNotEmpty($token);
        $this->assertTrue($admin->tokens()->first()->can('*'));
    }

    public function test_tenant_header_sets_tenant_context_in_middleware(): void
    {
        \Illuminate\Support\Facades\DB::table('tenants')->insert([
            'id' => 'org-default-123',
            'name' => 'Default Org',
            'slug' => 'default-org',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create(['tenant_id' => 'org-default-123']);

        $response = $this->actingAs($user)->withHeader('X-Tenant', 'org-custom-456')->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
        $this->assertEquals('org-custom-456', app(\App\Core\Tenancy\TenantManager::class)->get());
    }
}
