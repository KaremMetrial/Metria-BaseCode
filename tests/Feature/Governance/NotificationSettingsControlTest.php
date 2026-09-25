<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Domain\Models\User;
use Modules\Auth\Infrastructure\Notifications\LoginAlertNotification;
use Modules\Auth\Infrastructure\Notifications\WelcomeNotification;
use Modules\Governance\Infrastructure\Services\SettingsService;
use Modules\Shared\Infrastructure\Notifications\Channels\FcmChannel;
use Tests\TestCase;

/**
 * Notifications used to decide their own channels entirely in code — an
 * admin had no lever to turn one off, or restrict it to one channel,
 * without a deploy. NotificationGate reads Governance's runtime Settings
 * (the same ones PUT /api/v1/governance/settings/{key} edits) so that
 * lever now exists. These tests go through the real bound SettingsService,
 * not a mock, to prove the wiring — not just the gate class in isolation.
 */
class NotificationSettingsControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabling_a_notification_type_suppresses_every_channel(): void
    {
        app(SettingsService::class)->set('notifications.welcome.enabled', false);

        $user = User::factory()->create(['email' => 'new@example.com', 'phone' => '+201000000000']);
        $user->updateFcmDeviceToken('token-1');

        $this->assertSame([], (new WelcomeNotification)->via($user));
    }

    public function test_restricting_channels_narrows_but_never_widens_what_via_already_decided(): void
    {
        app(SettingsService::class)->set('notifications.login_alert.channels', ['mail']);

        $user = User::factory()->create(['email' => 'alerted@example.com']);
        $user->updateFcmDeviceToken('token-2');

        $notification = new LoginAlertNotification('127.0.0.1', 'Chrome', now()->toDateTimeString());
        $channels = $notification->via($user);

        $this->assertContains('mail', $channels);
        $this->assertNotContains(FcmChannel::class, $channels);
    }

    public function test_no_setting_means_every_channel_the_notification_would_pick_stays_available(): void
    {
        $user = User::factory()->create(['email' => 'default@example.com']);
        $user->updateFcmDeviceToken('token-3');

        $notification = new LoginAlertNotification('127.0.0.1', 'Chrome', now()->toDateTimeString());
        $channels = $notification->via($user);

        $this->assertContains('mail', $channels);
        $this->assertContains(FcmChannel::class, $channels);
    }
}
