<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordRecoveryAndLockoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_password_reset_token(): void
    {
        $user = User::factory()->create(['email' => 'recovery@example.com']);

        $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'recovery@example.com',
        ])->assertOk();

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'recovery@example.com',
        ]);
    }

    public function test_user_can_reset_password_with_valid_token_revoking_sessions(): void
    {
        $user = User::factory()->create([
            'email' => 'recovery@example.com',
            'password' => 'OldPassword123!',
        ]);

        // Create an existing token/session
        $user->createToken('old-device');
        $this->assertCount(1, $user->tokens);

        $rawToken = 'secret-reset-token-12345';
        DB::table('password_reset_tokens')->insert([
            'email' => 'recovery@example.com',
            'token' => Hash::make($rawToken),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'recovery@example.com',
            'token' => $rawToken,
            'password' => 'NewSecurePassword999!',
            'password_confirmation' => 'NewSecurePassword999!',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePassword999!', $user->password));

        // Ensure old tokens and sessions were revoked for security
        $this->assertCount(0, $user->tokens);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'recovery@example.com']);
    }

    public function test_reset_password_fails_with_invalid_or_expired_token(): void
    {
        $user = User::factory()->create(['email' => 'recovery@example.com']);

        DB::table('password_reset_tokens')->insert([
            'email' => 'recovery@example.com',
            'token' => Hash::make('valid-token'),
            'created_at' => now()->subHours(2), // Expired (> 60 mins)
        ]);

        $this->postJson('/api/v1/auth/password/reset', [
            'email' => 'recovery@example.com',
            'token' => 'valid-token',
            'password' => 'NewSecurePassword999!',
            'password_confirmation' => 'NewSecurePassword999!',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_reset_token');
    }

    public function test_account_lockout_occurs_after_too_many_failed_login_attempts(): void
    {
        $user = User::factory()->create(['email' => 'lockout@example.com', 'password' => 'Secret123!']);

        $throttleKey = 'login-attempts:lockout@example.com';
        RateLimiter::clear($throttleKey);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'lockout@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        // 6th attempt should trigger 429 Too Many Requests / login_locked
        $this->postJson('/api/v1/auth/login', [
            'email' => 'lockout@example.com',
            'password' => 'wrong-password',
        ])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'login_locked');
    }
}
