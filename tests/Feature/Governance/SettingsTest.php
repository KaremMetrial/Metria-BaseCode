<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Core\Tenancy\TenantManager;
use App\Domain\Auth\Models\User;
use App\Domain\Governance\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private SettingsService $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settings = app(SettingsService::class);
        Cache::clear();
    }

    public function test_setting_get_resolves_different_runtime_defaults_without_caching_them(): void
    {
        // Setting does not exist in DB: first call resolves default A
        $resA = $this->settings->get('non_existent_key', 'default_A');
        $this->assertEquals('default_A', $resA);

        // Second call resolves default B (should not be overwritten by cached default A)
        $resB = $this->settings->get('non_existent_key', 'default_B');
        $this->assertEquals('default_B', $resB);
    }

    public function test_setting_set_caches_value_and_invalidates_old_cache(): void
    {
        // First get default
        $this->assertEquals('default_val', $this->settings->get('my_config', 'default_val'));

        // Save setting
        $this->settings->set('my_config', 'custom_val');

        // Should return the saved value
        $this->assertEquals('custom_val', $this->settings->get('my_config', 'default_val'));

        // Delete setting
        $this->settings->forget('my_config');

        // Should fall back to default
        $this->assertEquals('another_default', $this->settings->get('my_config', 'another_default'));
    }

    public function test_settings_api_requires_proper_permissions(): void
    {
        $user = User::factory()->create();

        // 1. Unauthenticated requests should be blocked
        $this->getJson('/api/v1/governance/settings')->assertStatus(401);

        Sanctum::actingAs($user);

        // 2. Authenticated but unauthorized requests should get 403 Forbidden
        $this->getJson('/api/v1/governance/settings')->assertStatus(403);
        $this->putJson('/api/v1/governance/settings/my_config', ['value' => 'test'])->assertStatus(403);

        // 3. User with view permission can view settings
        $viewPermission = Permission::firstOrCreate(['name' => 'governance.settings.view', 'guard_name' => 'web']);
        $user->givePermissionTo($viewPermission);

        $this->getJson('/api/v1/governance/settings')->assertStatus(200);

        // Still forbidden to manage
        $this->putJson('/api/v1/governance/settings/my_config', ['value' => 'test'])->assertStatus(403);

        // 4. User with manage permission can update settings
        $managePermission = Permission::firstOrCreate(['name' => 'governance.settings.manage', 'guard_name' => 'web']);
        $user->givePermissionTo($managePermission);

        $this->putJson('/api/v1/governance/settings/my_config', [
            'value' => 'new_value',
            'description' => 'Test setting',
        ])->assertStatus(200);

        $this->assertEquals('new_value', $this->settings->get('my_config'));
    }

    public function test_settings_service_isolates_cache_and_db_per_tenant(): void
    {
        $manager = app(TenantManager::class);

        // Tenant A sets setting
        $manager->set('tenant-a');
        $this->settings->set('tax_rate', 0.15);
        $this->assertEquals(0.15, $this->settings->get('tax_rate'));

        // Tenant B sets setting with same key
        $manager->set('tenant-b');
        $this->settings->set('tax_rate', 0.05);
        $this->assertEquals(0.05, $this->settings->get('tax_rate'));

        // Switch back to Tenant A and verify cache/db isolation
        $manager->set('tenant-a');
        $this->assertEquals(0.15, $this->settings->get('tax_rate'));
    }
}
