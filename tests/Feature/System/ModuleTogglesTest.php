<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use Tests\TestCase;

/**
 * A simple, single-purpose project doesn't need Payment/Wallet/Media/
 * Webhook/Communication/Governance/OAuth-provider-management cluttering its
 * API surface. config/modules.php lets each be turned off independently;
 * this proves the routes actually disappear (not just that the config key
 * exists) and that the always-on core (Auth) is unaffected.
 */
class ModuleTogglesTest extends TestCase
{
    /** @var array<int, string> */
    private array $envVarsToReset = [];

    protected function tearDown(): void
    {
        foreach ($this->envVarsToReset as $var) {
            putenv($var);
        }
        $this->envVarsToReset = [];

        parent::tearDown();
    }

    /**
     * Config is read from env at boot time (no config:cache in tests), so
     * disabling a module has to happen via env before refreshApplication()
     * re-registers routes — mutating the already-booted config() array and
     * refreshing would just re-read the same defaults from .env/env vars.
     */
    private function disableModules(string ...$keys): void
    {
        foreach ($keys as $key) {
            $var = 'MODULE_'.strtoupper($key).'_ENABLED';
            putenv("{$var}=false");
            $this->envVarsToReset[] = $var;
        }
        $this->refreshApplication();

        // sqlite :memory: is per-connection: a fresh Application instance
        // means a fresh, unmigrated database, so re-run what RefreshDatabase
        // already did once for the original instance before this refresh.
        $this->artisan('migrate', ['--seed' => true]);
    }

    public function test_disabling_a_module_removes_its_routes(): void
    {
        $this->disableModules('payment');

        $this->postJson('/api/v1/payments', [])->assertStatus(404);
    }

    public function test_disabling_all_optional_modules_leaves_core_auth_routes_intact(): void
    {
        $this->disableModules(
            'payment',
            'wallet',
            'media',
            'webhook',
            'communication',
            'governance',
            'integration_oauth',
        );

        foreach ([
            '/api/v1/payments',
            '/api/v1/wallet',
            '/api/v1/media/presign',
            '/api/v1/webhook-endpoints',
            '/api/v1/auth/oauth-providers',
            '/api/v1/governance/settings',
        ] as $uri) {
            $this->getJson($uri)->assertStatus(404);
        }

        // Auth itself is a core primitive and is never gated.
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'toggle-test@example.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ])->assertCreated();
    }

    public function test_modules_stay_enabled_by_default(): void
    {
        $this->assertTrue(config('modules.payment'));
        $this->assertTrue(config('modules.wallet'));
        $this->assertTrue(config('modules.media'));
        $this->assertTrue(config('modules.webhook'));
        $this->assertTrue(config('modules.communication'));
        $this->assertTrue(config('modules.governance'));
        $this->assertTrue(config('modules.integration_oauth'));
    }
}
