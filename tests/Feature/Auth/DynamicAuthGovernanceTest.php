<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Auth\Models\User;
use App\Domain\Governance\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicAuthGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_login_succeeds_when_enabled(): void
    {
        $user = User::factory()->create(['password' => 'Secret123!']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_password_login_blocked_when_disabled_by_governance(): void
    {
        app(SettingsService::class)->set('auth.methods.password_enabled', false);

        $user = User::factory()->create(['password' => 'Secret123!']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'auth_method_disabled');
    }

    public function test_otp_send_blocked_when_disabled_by_governance(): void
    {
        app(SettingsService::class)->set('auth.methods.otp_enabled', false);

        $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => 'test@example.com',
            'action' => 'login',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'auth_method_disabled');
    }

    public function test_social_login_blocked_when_disabled_by_governance(): void
    {
        app(SettingsService::class)->set('auth.methods.social_enabled', false);

        $this->postJson('/api/v1/auth/social/google/callback', [
            'id' => '123456789',
            'email' => 'social@example.com',
            'name' => 'Social User',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'auth_method_disabled');
    }
}
