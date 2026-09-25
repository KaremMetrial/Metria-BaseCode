<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Auth\Domain\Events\OtpGenerated;
use Modules\Auth\Domain\Models\User;
use Modules\Auth\Infrastructure\Notifications\LoginAlertNotification;
use Modules\Auth\Infrastructure\Notifications\OtpNotification;
use Modules\Auth\Infrastructure\Notifications\WelcomeNotification;
use Tests\TestCase;

/**
 * Every Notification class used to build its subject/body/SMS/FCM text from
 * a literal English string passed straight to __() — e.g. __('Hello!').
 * Laravel's __() only translates a literal (non "file.key") string via a
 * lang/{locale}.json map, and this project has none, so those calls always
 * rendered in English regardless of app()->getLocale() — a French/Arabic
 * user got an English OTP email with no way to fix it short of adding a
 * .json file. This locks in the fix: real auth.notifications.* keys, with
 * both lang/en and lang/ar entries, actually followed by the app's locale.
 */
class NotificationLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app()->setLocale('en');
        parent::tearDown();
    }

    public function test_otp_notification_mail_and_sms_render_in_arabic(): void
    {
        app()->setLocale('ar');

        $notification = new OtpNotification('123456');
        $user = User::factory()->make(['email' => 'guest@example.com']);

        $mail = $notification->toMail($user);
        $this->assertStringContainsString('رمز التحقق', $mail->subject);

        $sms = $notification->toSms($user);
        $this->assertStringContainsString('رمز التحقق', $sms);
        $this->assertStringContainsString('123456', $sms);
    }

    public function test_otp_notification_mail_and_sms_render_in_english_by_default(): void
    {
        app()->setLocale('en');

        $notification = new OtpNotification('123456');
        $user = User::factory()->make(['email' => 'guest@example.com']);

        $this->assertStringContainsString('OTP Verification Code', $notification->toMail($user)->subject);
        $this->assertStringContainsString('OTP verification code', $notification->toSms($user));
    }

    public function test_welcome_notification_renders_in_the_locale_it_is_built_under(): void
    {
        $user = User::factory()->make(['name' => 'سارة', 'email' => 'sara@example.com']);

        app()->setLocale('ar');
        $notification = new WelcomeNotification;
        $this->assertStringContainsString('أهلاً بك', $notification->toMail($user)->subject);
        $this->assertStringContainsString('سارة', $notification->toSms($user));

        app()->setLocale('en');
        $notification = new WelcomeNotification;
        $this->assertStringContainsString('Welcome to', $notification->toMail($user)->subject);
    }

    public function test_login_alert_notification_renders_in_arabic(): void
    {
        app()->setLocale('ar');

        $notification = new LoginAlertNotification('127.0.0.1', 'Chrome on macOS', now()->toDateTimeString());
        $user = User::factory()->make(['name' => 'Ali']);

        $this->assertStringContainsString('تنبيه أمني', $notification->toMail($user)->subject);
        $this->assertStringContainsString('تنبيه أمني', $notification->toFcm($user)['title']);
    }

    public function test_a_users_saved_locale_drives_their_queued_notifications(): void
    {
        $arabicUser = User::factory()->create(['locale' => 'ar']);
        $englishUser = User::factory()->create(['locale' => 'en']);

        $this->assertSame('ar', $arabicUser->preferredLocale());
        $this->assertSame('en', $englishUser->preferredLocale());
    }

    public function test_a_user_with_no_saved_locale_falls_back_to_the_app_default(): void
    {
        // locale is NOT NULL in the schema, so exercise the fallback branch
        // on an unpersisted instance rather than fighting the DB constraint.
        $user = User::factory()->make();
        $user->locale = null;

        $this->assertSame(config('localization.fallback', 'en'), $user->preferredLocale());
    }

    /**
     * SendOtpNotification (queued) has no HTTP request of its own to read
     * the requester's locale from — it has to travel on the event.
     */
    public function test_otp_generated_event_carries_the_dispatchers_locale(): void
    {
        Event::fake([OtpGenerated::class]);
        app()->setLocale('ar');

        $this->postJson('/api/v1/auth/otp/send?lang=ar', [
            'identifier' => 'guest@example.com',
            'action' => 'login',
        ])->assertOk();

        Event::assertDispatched(OtpGenerated::class, fn (OtpGenerated $event) => $event->locale === 'ar');
    }
}
