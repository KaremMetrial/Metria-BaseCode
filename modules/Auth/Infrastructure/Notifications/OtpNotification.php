<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Shared\Infrastructure\Notifications\Channels\SmsChannel;

class OtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        // Check if notifiable has email or a mail route
        $email = $notifiable instanceof Model
            ? $notifiable->getAttribute('email')
            : (property_exists($notifiable, 'email') ? $notifiable->email : null);

        $hasEmail = (is_string($email) && str_contains($email, '@'))
            || (method_exists($notifiable, 'routeNotificationFor') && $notifiable->routeNotificationFor('mail') !== null);

        if ($hasEmail) {
            $channels[] = 'mail';
        }

        // Check if notifiable has phone or an SMS route
        $phone = $notifiable instanceof Model
            ? $notifiable->getAttribute('phone')
            : (property_exists($notifiable, 'phone') ? $notifiable->phone : null);

        $hasPhone = (is_scalar($phone) && ! empty($phone))
            || (method_exists($notifiable, 'routeNotificationFor') && $notifiable->routeNotificationFor('sms') !== null);

        if ($hasPhone) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cfgApp = config('app.name', 'Enterprise Base');
        $appName = is_scalar($cfgApp) ? (string) $cfgApp : 'Enterprise Base';

        return (new MailMessage)
            ->subject(__('auth.notifications.otp.mail_subject', ['app' => $appName]))
            ->greeting(__('auth.notifications.otp.greeting'))
            ->line(__('auth.notifications.otp.intro'))
            ->line(__('auth.notifications.otp.code_line'))
            ->line("## {$this->code}")
            ->line(__('auth.notifications.otp.validity'))
            ->salutation(__('auth.notifications.common.regards')."\n".__('auth.notifications.common.team', ['app' => $appName]));
    }

    public function toSms(object $notifiable): string
    {
        return __('auth.notifications.otp.sms', ['code' => $this->code]);
    }
}
