<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvancedMfaAndSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_enable_and_confirm_mfa(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $enableRes = $this->postJson('/api/v1/auth/mfa/enable');
        $enableRes->assertOk()->assertJsonStructure(['data' => ['secret', 'qr_url', 'recovery_codes']]);

        $secret = $enableRes->json('data.secret');

        $this->postJson('/api/v1/auth/mfa/confirm', [
            'code' => $this->generateTotp($secret),
        ])->assertOk();

        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertTrue($user->hasMfaEnabled());
    }

    public function test_login_challenge_requires_mfa_code_when_enabled(): void
    {
        $user = User::factory()->create(['password' => 'Secret123!']);
        $user->two_factor_secret = 'JBSWY3DPEHPK3PXP';
        $user->two_factor_confirmed_at = now();
        $user->save();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.mfa_required', true)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_user_can_verify_mfa_login_with_totp_code(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = User::factory()->create(['password' => 'Secret123!']);
        $user->two_factor_secret = $secret;
        $user->two_factor_confirmed_at = now();
        $user->save();

        $response = $this->postJson('/api/v1/auth/mfa/verify', [
            'email' => $user->email,
            'password' => 'Secret123!',
            'code' => $this->generateTotp($secret),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_user_can_verify_mfa_login_with_recovery_code(): void
    {
        $user = User::factory()->create(['password' => 'Secret123!']);
        $recoveryCode = 'ABCDE-12345';
        $user->two_factor_secret = 'JBSWY3DPEHPK3PXP';
        $user->two_factor_recovery_codes = json_encode([bcrypt($recoveryCode)]);
        $user->two_factor_confirmed_at = now();
        $user->save();

        $response = $this->postJson('/api/v1/auth/mfa/verify', [
            'email' => $user->email,
            'password' => 'Secret123!',
            'code' => $recoveryCode,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id);

        $user->refresh();
        $remaining = json_decode((string) $user->two_factor_recovery_codes, true);
        $this->assertEmpty($remaining);
    }

    public function test_user_can_view_and_revoke_active_sessions(): void
    {
        $user = User::factory()->create(['password' => 'Secret123!']);

        // Login creates session
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Secret123!',
        ]);

        $token = $loginRes->json('data.token');
        $this->assertDatabaseHas('user_sessions', ['user_id' => $user->id]);

        $session = UserSession::query()->where('user_id', $user->id)->firstOrFail();

        // View sessions
        $this->withToken($token)
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonPath('data.sessions.0.id', $session->id);

        // Revoke session
        $this->withToken($token)
            ->deleteJson("/api/v1/auth/sessions/{$session->id}")
            ->assertOk();

        $this->assertDatabaseMissing('user_sessions', ['id' => $session->id]);
    }
}
