<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Auth\Domain\Models\User;
use Modules\Shared\Infrastructure\Notifications\Channels\FcmChannel;
use Modules\Shared\Infrastructure\Notifications\Channels\SmsChannel;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        $channels = [];

        $email = $notifiable instanceof Model
            ? $notifiable->getAttribute('email')
            : (property_exists($notifiable, 'email') ? $notifiable->email : null);

        $hasEmail = (is_string($email) && str_contains($email, '@') && ! str_ends_with($email, '@otp.local'))
            || (method_exists($notifiable, 'routeNotificationFor') && $notifiable->routeNotificationFor('mail') !== null);

        if ($hasEmail) {
            $channels[] = 'mail';
        }

        $phone = $notifiable instanceof Model
            ? $notifiable->getAttribute('phone')
            : (property_exists($notifiable, 'phone') ? $notifiable->phone : null);

        $hasPhone = (is_string($phone) && $phone !== '')
            || (method_exists($notifiable, 'routeNotificationFor') && $notifiable->routeNotificationFor('sms') !== null);

        if ($hasPhone) {
            $channels[] = SmsChannel::class;
        }

        // If the user has active FCM device tokens, send a push notification too!
        if ($notifiable instanceof User && $notifiable->fcmDeviceTokens()->exists()) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cfgApp = config('app.name', 'Enterprise Base');
        $appName = is_scalar($cfgApp) ? (string) $cfgApp : 'Enterprise Base';

        return (new MailMessage)
            ->subject(__('auth.notifications.welcome.mail_subject', ['app' => $appName]))
            ->greeting(__('auth.notifications.welcome.greeting', ['name' => $this->getName($notifiable)]))
            ->line(__('auth.notifications.welcome.intro'))
            ->line(__('auth.notifications.welcome.body'))
            ->action(__('auth.notifications.welcome.action'), url('/'))
            ->line(__('auth.notifications.welcome.support'))
            ->salutation(__('auth.notifications.common.regards')."\n".__('auth.notifications.common.team', ['app' => $appName]));
    }

    public function toSms(object $notifiable): string
    {
        $cfgApp = config('app.name', 'Enterprise Base');
        $appName = is_scalar($cfgApp) ? (string) $cfgApp : 'Enterprise Base';

        return __('auth.notifications.welcome.sms', [
            'app' => $appName,
            'name' => $this->getName($notifiable),
        ]);
    }

    public function toFcm(object $notifiable): array
    {
        $cfgApp = config('app.name', 'Enterprise Base');
        $appName = is_scalar($cfgApp) ? (string) $cfgApp : 'Enterprise Base';

        return [
            'title' => __('auth.notifications.welcome.fcm_title', ['app' => $appName]),
            'body' => __('auth.notifications.welcome.fcm_body', ['name' => $this->getName($notifiable)]),
            'data' => [
                'type' => 'welcome',
                'action' => 'open_dashboard',
            ],
        ];
    }

    private function getName(object $notifiable): string
    {
        $name = $notifiable instanceof Model
            ? $notifiable->getAttribute('name')
            : (property_exists($notifiable, 'name') ? $notifiable->name : null);

        return is_string($name) ? $name : 'User';
    }
}
