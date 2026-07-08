<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_resend_cooldown_throws_429(): void
    {
        // 1. First OTP request succeeds
        $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => '+201011112222',
            'action' => 'login',
        ])->assertOk();

        // 2. Second immediate OTP request fails with 429 otp_throttle
        $response = $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => '+201011112222',
            'action' => 'login',
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'otp_throttle');

        // 3. Travel 61 seconds into the future
        Carbon::setTestNow(now()->addSeconds(61));

        // 4. Request succeeds again
        $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => '+201011112222',
            'action' => 'login',
        ])->assertOk();

        // Reset time
        Carbon::setTestNow();
    }

    public function test_login_lockout_after_five_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'lockout@example.com',
            'password' => 'Secret123!',
        ]);

        // 1. Send 5 failed login attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'lockout@example.com',
                'password' => 'WrongPassword',
            ])->assertStatus(401)
                ->assertJsonPath('error.code', 'invalid_credentials');
        }

        // 2. The 6th attempt (even with correct password) is locked out
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'lockout@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'login_locked');

        // 3. Travel 5 minutes (301 seconds) into the future
        Carbon::setTestNow(now()->addSeconds(301));

        // 4. Login succeeds with correct password
        $this->postJson('/api/v1/auth/login', [
            'email' => 'lockout@example.com',
            'password' => 'Secret123!',
        ])->assertOk();

        // Reset time
        Carbon::setTestNow();
    }

    public function test_pruning_personal_access_tokens(): void
    {
        $user = User::factory()->create();

        // Create 3 tokens
        $token1 = $user->createToken('device1');
        $token2 = $user->createToken('device2');
        $token3 = $user->createToken('device3');

        // Modify token1 to be created 31 days ago (never used)
        DB::table('personal_access_tokens')
            ->where('id', $token1->accessToken->id)
            ->update([
                'created_at' => now()->subDays(31),
                'last_used_at' => null,
            ]);

        // Modify token2 to be last used 31 days ago
        DB::table('personal_access_tokens')
            ->where('id', $token2->accessToken->id)
            ->update([
                'created_at' => now()->subDays(35),
                'last_used_at' => now()->subDays(31),
            ]);

        // Token3 remains active/new

        // Run the command
        $exitCode = Artisan::call('auth:prune-tokens');
        $this->assertSame(0, $exitCode);

        // Assert token1 and token2 are deleted, token3 remains
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token1->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token2->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token3->accessToken->id]);
    }

    public function test_mfa_totp_code_cannot_be_replayed(): void
    {
        $secret = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $user = User::factory()->create([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $mfaService = app(\App\Domain\Auth\Services\MfaService::class);
        $code = $this->generateTotp($secret);

        // First verification should succeed
        $this->assertTrue($mfaService->verify($user, $code));

        // Second verification with the same code (replay) should fail
        $this->assertFalse($mfaService->verify($user, $code));
    }

    private function generateTotp(string $secret): string
    {
        $secret = strtoupper($secret);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
            $char = $secret[$i];
            $val = strpos($alphabet, $char);
            if ($val === false) continue;
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }
        $decoded = '';
        for ($i = 0, $len = strlen($bits); $i + 8 <= $len; $i += 8) {
            $decoded .= chr((int) bindec(substr($bits, $i, 8)));
        }

        $timeSlice = (int) floor(time() / 30);
        $binaryTimeSlice = pack('N', 0).pack('N', $timeSlice);
        $hmac = hash_hmac('sha1', $binaryTimeSlice, $decoded, true);
        $offsetIndex = ord($hmac[19]) & 0xf;
        $value = ((ord($hmac[$offsetIndex]) & 0x7f) << 24)
            | ((ord($hmac[$offsetIndex + 1]) & 0xff) << 16)
            | ((ord($hmac[$offsetIndex + 2]) & 0xff) << 8)
            | (ord($hmac[$offsetIndex + 3]) & 0xff);
        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
