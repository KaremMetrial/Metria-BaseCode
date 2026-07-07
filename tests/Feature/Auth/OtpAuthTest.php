<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Auth\Events\OtpFailed;
use App\Domain\Auth\Events\OtpGenerated;
use App\Domain\Auth\Events\OtpVerified;
use App\Domain\Auth\Events\UserLoggedInByOtp;
use App\Domain\Auth\Models\OtpCode;
use App\Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class OtpAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([
            OtpGenerated::class,
            OtpVerified::class,
            OtpFailed::class,
            UserLoggedInByOtp::class,
        ]);
        Mail::fake();
    }

    public function test_can_request_otp_by_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => '+201012345678',
            'action' => 'register',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('otps', [
            'identifier' => '+201012345678',
            'action' => 'register',
            'guard' => 'web',
        ]);

        Event::assertDispatched(OtpGenerated::class, function ($event) {
            return $event->identifier === '+201012345678' && $event->action === 'register';
        });
    }

    public function test_can_request_otp_by_email(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => 'test@example.com',
            'action' => 'login',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('otps', [
            'identifier' => 'test@example.com',
            'action' => 'login',
        ]);

        Event::assertDispatched(OtpGenerated::class);
    }

    public function test_can_register_using_otp(): void
    {
        // 1. Pre-generate OTP
        $otp = OtpCode::query()->create([
            'id' => Str::uuid()->toString(),
            'identifier' => '+201012345678',
            'code' => '123456',
            'action' => 'register',
            'guard' => 'web',
            'expires_at' => now()->addMinutes(10),
        ]);

        // 2. Perform register
        $response = $this->postJson('/api/v1/auth/otp/register', [
            'name' => 'OTP User',
            'identifier' => '+201012345678',
            'code' => '123456',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'phone', 'email'], 'token']]);

        // 3. Assert DB changes
        $this->assertDatabaseHas('users', [
            'phone' => '+201012345678',
            'name' => 'OTP User',
        ]);

        $user = User::query()->where('phone', '+201012345678')->firstOrFail();
        $this->assertTrue($user->hasRole('customer'));

        // Assert provisioned defaults (Wallet via listener)
        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
        ]);

        // Assert OTP is marked verified
        $this->assertNotNull($otp->fresh()->verified_at);

        Event::assertDispatched(OtpVerified::class);
    }

    public function test_can_login_using_otp(): void
    {
        $user = User::factory()->create([
            'phone' => '+201000000000',
            'email' => 'login@example.com',
        ]);

        $otp = OtpCode::query()->create([
            'id' => Str::uuid()->toString(),
            'identifier' => '+201000000000',
            'code' => '654321',
            'action' => 'login',
            'guard' => 'web',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/auth/otp/login', [
            'identifier' => '+201000000000',
            'code' => '654321',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertNotNull($otp->fresh()->verified_at);

        Event::assertDispatched(OtpVerified::class);
        Event::assertDispatched(UserLoggedInByOtp::class);
    }

    public function test_otp_login_fails_with_invalid_code(): void
    {
        $user = User::factory()->create([
            'phone' => '+201000000000',
        ]);

        $otp = OtpCode::query()->create([
            'id' => Str::uuid()->toString(),
            'identifier' => '+201000000000',
            'code' => '654321',
            'action' => 'login',
            'guard' => 'web',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/auth/otp/login', [
            'identifier' => '+201000000000',
            'code' => '000000',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'otp_invalid');

        $this->assertEquals(1, $otp->fresh()->attempts);
        Event::assertDispatched(OtpFailed::class);
    }

    public function test_otp_login_locks_after_max_attempts(): void
    {
        $user = User::factory()->create([
            'phone' => '+201000000000',
        ]);

        $otp = OtpCode::query()->create([
            'id' => Str::uuid()->toString(),
            'identifier' => '+201000000000',
            'code' => '654321',
            'action' => 'login',
            'guard' => 'web',
            'attempts' => 5,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/auth/otp/login', [
            'identifier' => '+201000000000',
            'code' => '654321',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'otp_max_attempts');

        Event::assertDispatched(OtpFailed::class);
    }

    public function test_multi_guard_otp_login_resolution(): void
    {
        // 1. Configure custom admin guard dynamically
        config()->set('auth.guards.admin', [
            'driver' => 'session',
            'provider' => 'admins',
        ]);
        config()->set('auth.providers.admins', [
            'driver' => 'eloquent',
            'model' => User::class, // Reuse User model for testing
        ]);

        $user = User::factory()->create([
            'email' => 'admin@example.com',
        ]);

        OtpCode::query()->create([
            'id' => Str::uuid()->toString(),
            'identifier' => 'admin@example.com',
            'code' => '999999',
            'action' => 'login',
            'guard' => 'admin',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/auth/otp/login', [
            'identifier' => 'admin@example.com',
            'code' => '999999',
            'guard' => 'admin',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id);

        Event::assertDispatched(UserLoggedInByOtp::class, function ($event) {
            return $event->guard === 'admin';
        });
    }
}
