<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Notifications\Support;

use Modules\Shared\Domain\Contracts\RuntimeSettings;

/**
 * Runtime, per-notification-type control over whether a notification sends
 * at all and which channels it's allowed to use — backed by Governance's
 * Settings (PUT /api/v1/governance/settings/{key}), so an admin can turn
 * these on/off without a deploy.
 *
 * Two settings per notification type, both optional (missing = fully on):
 *   notifications.{type}.enabled   bool   — false suppresses it completely
 *   notifications.{type}.channels  array  — subset of ['mail','sms','fcm'];
 *                                            omitted/null allows every channel
 *
 * A notification's own via() still decides which channels are *possible*
 * (does this user have an email? active FCM tokens?) — this only narrows
 * that set further, it never adds a channel the notification didn't already
 * consider. Filtering everything down to zero channels means the
 * notification silently doesn't send — that's the deliberate effect of
 * turning it off, not a bug.
 */
final class NotificationGate
{
    public function __construct(private readonly RuntimeSettings $settings) {}

    public function enabled(string $type): bool
    {
        return (bool) $this->settings->get("notifications.{$type}.enabled", true);
    }

    /**
     * @param  array<int, string>  $channels  channel identifiers already
     *                                        decided possible by the notification's own via() — e.g.
     *                                        ['mail', SmsChannel::class] — filtered against the setting
     * @return array<int, string>
     */
    public function filterChannels(string $type, array $channels): array
    {
        if (! $this->enabled($type)) {
            return [];
        }

        $allowed = $this->settings->get("notifications.{$type}.channels");

        if (! is_array($allowed) || $allowed === []) {
            return $channels;
        }

        $allowedKinds = array_map(self::kind(...), $allowed);

        return array_values(array_filter(
            $channels,
            fn (string $channel) => in_array(self::kind($channel), $allowedKinds, true)
        ));
    }

    /**
     * Maps a channel identifier ('mail', a fully-qualified *SmsChannel /
     * *FcmChannel class-string) to the short kind an admin would type into
     * a settings value: 'mail', 'sms', 'fcm'.
     */
    private static function kind(string $channel): string
    {
        return match (true) {
            $channel === 'mail' => 'mail',
            str_ends_with($channel, 'SmsChannel') => 'sms',
            str_ends_with($channel, 'FcmChannel') => 'fcm',
            default => $channel,
        };
    }
}
