<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Notifications\Channels\FcmChannel;
use App\Domain\Auth\Models\OtpCode;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Notifications\LoginAlertNotification;
use App\Domain\Auth\Notifications\OtpNotification;
use App\Domain\Auth\Notifications\WelcomeNotification;
use App\Domain\Integration\Sms\SmsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class NotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_otp_generation_triggers_otp_notification_via_mail_for_emails(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => 'guest@example.com',
            'action' => 'login',
        ])->assertOk();

        Notification::assertSentTo(
            new AnonymousNotifiable,
            OtpNotification::class,
            function (OtpNotification $notification, array $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'guest@example.com'
                    && in_array('mail', $channels, true);
            }
        );
    }

    public function test_otp_generation_triggers_otp_notification_via_sms_for_phones(): void
    {
        // Mock SmsManager to ensure SmsChannel resolves it and forwards
        $this->mock(SmsManager::class, function (MockInterface $mock) {
            $mock->shouldReceive('driver->send')
                ->once()
                ->with('+201011111111', \Mockery::on(function ($msg) {
                    return str_contains($msg, 'Your OTP verification code is');
                }));
        });

        // Trigger SMS sending
        $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => '+201011111111',
            'action' => 'login',
        ])->assertOk();
    }

    public function test_standard_registration_triggers_welcome_notification(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+201012345678',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ])->assertCreated();

        $user = User::query()->where('email', 'john.doe@example.com')->firstOrFail();

        Notification::assertSentTo($user, WelcomeNotification::class);
    }

    public function test_otp_registration_triggers_welcome_notification(): void
    {
        Notification::fake();

        OtpCode::query()->create([
            'id' => Str::uuid()->toString(),
            'identifier' => '+201044444444',
            'code' => '123456',
            'action' => 'register',
            'guard' => 'web',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/v1/auth/otp/register', [
            'name' => 'OTP New User',
            'identifier' => '+201044444444',
            'code' => '123456',
        ])->assertCreated();

        $user = User::query()->where('phone', '+201044444444')->firstOrFail();

        Notification::assertSentTo($user, WelcomeNotification::class);
    }

    public function test_standard_login_triggers_login_alert_notification_via_mail_and_fcm(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'login-alert@example.com',
            'password' => 'Secret123!',
        ]);

        // Register FCM device token so FcmChannel triggers
        $user->updateFcmDeviceToken('user-fcm-token-999');

        // Trigger Login
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login-alert@example.com',
            'password' => 'Secret123!',
        ]);

        $response->assertOk();

        Notification::assertSentTo(
            $user,
            LoginAlertNotification::class,
            function (LoginAlertNotification $notification, array $channels) {
                return in_array('mail', $channels, true)
                    && in_array(FcmChannel::class, $channels, true);
            }
        );
    }

    public function test_otp_login_triggers_login_alert_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'phone' => '+201055555555',
        ]);

        OtpCode::query()->create([
            'id' => Str::uuid()->toString(),
            'identifier' => '+201055555555',
            'code' => '987654',
            'action' => 'login',
            'guard' => 'web',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/v1/auth/otp/login', [
            'identifier' => '+201055555555',
            'code' => '987654',
        ])->assertOk();

        Notification::assertSentTo($user, LoginAlertNotification::class);
    }
}
