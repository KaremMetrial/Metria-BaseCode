<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Auth\Models\OtpCode;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Services\SendPushToUser;
use App\Domain\Integration\Push\FcmPushProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Mockery\MockInterface;
use Tests\TestCase;

class FcmTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_register_fcm_token_during_standard_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'FCM Register User',
            'email' => 'fcm-register@example.com',
            'phone' => '+201011111111',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'device_token' => 'fcm-token-register-123',
            'device_id' => 'device-id-register',
            'platform' => 'android',
        ]);

        $response->assertCreated();

        $user = User::query()->where('email', 'fcm-register@example.com')->firstOrFail();

        $this->assertDatabaseHas('fcm_device_tokens', [
            'user_id' => $user->id,
            'device_token' => 'fcm-token-register-123',
            'device_id' => 'device-id-register',
            'platform' => 'android',
        ]);
    }

    public function test_can_register_fcm_token_during_standard_login(): void
    {
        $user = User::factory()->create([
            'email' => 'fcm-login@example.com',
            'password' => 'Secret123!',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'fcm-login@example.com',
            'password' => 'Secret123!',
            'device_token' => 'fcm-token-login-123',
            'device_id' => 'device-id-login',
            'platform' => 'ios',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('fcm_device_tokens', [
            'user_id' => $user->id,
            'device_token' => 'fcm-token-login-123',
            'device_id' => 'device-id-login',
            'platform' => 'ios',
        ]);
    }

    public function test_can_register_fcm_token_during_otp_register(): void
    {
        OtpCode::query()->create([
            'id' => Str::uuid()->toString(),
            'identifier' => '+201022222222',
            'code' => '123456',
            'action' => 'register',
            'guard' => 'web',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/auth/otp/register', [
            'name' => 'OTP FCM User',
            'identifier' => '+201022222222',
            'code' => '123456',
            'device_token' => 'fcm-token-otp-register',
            'device_id' => 'device-id-otp-reg',
            'platform' => 'android',
        ]);

        $response->assertCreated();

        $user = User::query()->where('phone', '+201022222222')->firstOrFail();

        $this->assertDatabaseHas('fcm_device_tokens', [
            'user_id' => $user->id,
            'device_token' => 'fcm-token-otp-register',
            'device_id' => 'device-id-otp-reg',
            'platform' => 'android',
        ]);
    }

    public function test_can_register_fcm_token_during_otp_login(): void
    {
        $user = User::factory()->create([
            'phone' => '+201033333333',
        ]);

        OtpCode::query()->create([
            'id' => Str::uuid()->toString(),
            'identifier' => '+201033333333',
            'code' => '654321',
            'action' => 'login',
            'guard' => 'web',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/auth/otp/login', [
            'identifier' => '+201033333333',
            'code' => '654321',
            'device_token' => 'fcm-token-otp-login',
            'device_id' => 'device-id-otp-login',
            'platform' => 'web',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('fcm_device_tokens', [
            'user_id' => $user->id,
            'device_token' => 'fcm-token-otp-login',
            'device_id' => 'device-id-otp-login',
            'platform' => 'web',
        ]);
    }

    public function test_can_update_fcm_token_via_authenticated_route(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/auth/fcm-token', [
                'device_token' => 'fcm-token-direct-update',
                'device_id' => 'device-id-direct',
                'device_name' => 'My Phone',
                'platform' => 'ios',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('fcm_device_tokens', [
            'user_id' => $user->id,
            'device_token' => 'fcm-token-direct-update',
            'device_id' => 'device-id-direct',
            'device_name' => 'My Phone',
            'platform' => 'ios',
        ]);
    }

    public function test_token_update_removes_stale_records_from_other_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Register token to User B
        $userB->updateFcmDeviceToken('shared-fcm-token', 'device-123', 'Phone B', 'android');

        $this->assertDatabaseHas('fcm_device_tokens', [
            'user_id' => $userB->id,
            'device_token' => 'shared-fcm-token',
        ]);

        // Register same token to User A
        $userA->updateFcmDeviceToken('shared-fcm-token', 'device-123', 'Phone A', 'android');

        // Token should now be linked to A, and unlinked/deleted from B
        $this->assertDatabaseHas('fcm_device_tokens', [
            'user_id' => $userA->id,
            'device_token' => 'shared-fcm-token',
        ]);

        $this->assertDatabaseMissing('fcm_device_tokens', [
            'user_id' => $userB->id,
            'device_token' => 'shared-fcm-token',
        ]);
    }

    public function test_logout_removes_fcm_token_if_provided(): void
    {
        $user = User::factory()->create();
        $user->updateFcmDeviceToken('logout-token-123');

        $this->assertDatabaseHas('fcm_device_tokens', [
            'user_id' => $user->id,
            'device_token' => 'logout-token-123',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/auth/logout', [
                'device_token' => 'logout-token-123',
            ]);

        $response->assertNoContent();

        $this->assertDatabaseMissing('fcm_device_tokens', [
            'device_token' => 'logout-token-123',
        ]);
    }

    public function test_send_push_to_user_invokes_fcm_and_prunes_dead_tokens(): void
    {
        $user = User::factory()->create();
        $user->updateFcmDeviceToken('valid-token', 'dev-1');
        $user->updateFcmDeviceToken('dead-token', 'dev-2');

        $this->mock(FcmPushProvider::class, function (MockInterface $mock) {
            // First call succeeds
            $mock->shouldReceive('send')
                ->once()
                ->with('valid-token', 'Title', 'Body', [])
                ->andReturn('projects/test/messages/msg-valid');

            // Second call fails with NotFound
            $mock->shouldReceive('send')
                ->once()
                ->with('dead-token', 'Title', 'Body', [])
                ->andThrow(new NotFound('Token not registered.'));
        });

        $service = app(SendPushToUser::class);
        $results = $service($user, 'Title', 'Body');

        // Results should contain the successful send
        $this->assertArrayHasKey('valid-token', $results);
        $this->assertSame('projects/test/messages/msg-valid', $results['valid-token']);

        // Database should still contain the valid token
        $this->assertDatabaseHas('fcm_device_tokens', [
            'user_id' => $user->id,
            'device_token' => 'valid-token',
        ]);

        // Dead token should have been pruned/deleted
        $this->assertDatabaseMissing('fcm_device_tokens', [
            'user_id' => $user->id,
            'device_token' => 'dead-token',
        ]);
    }
}
