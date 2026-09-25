<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Shared\Infrastructure\Notifications\Channels\FcmChannel;

class LoginAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $ipAddress,
        public readonly string $userAgent,
        public readonly string $loginTime
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        $email = $notifiable instanceof Model
            ? $notifiable->getAttribute('email')
            : (property_exists($notifiable, 'email') ? $notifiable->email : null);

        if (is_string($email) && str_contains($email, '@') && ! str_ends_with($email, '@otp.local')) {
            $channels[] = 'mail';
        }

        if (method_exists($notifiable, 'fcmDeviceTokens')) {
            $tokens = $notifiable->fcmDeviceTokens();
            if ($tokens instanceof Relation && $tokens->exists()) {
                $channels[] = FcmChannel::class;
            }
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cfgApp = config('app.name', 'Enterprise Base');
        $appName = is_scalar($cfgApp) ? (string) $cfgApp : 'Enterprise Base';

        return (new MailMessage)
            ->subject(__('auth.notifications.login_alert.mail_subject'))
            ->greeting(__('auth.notifications.login_alert.greeting', ['name' => $this->getName($notifiable)]))
            ->line(__('auth.notifications.login_alert.intro'))
            ->line('**'.__('auth.notifications.login_alert.details_label').'**')
            ->line('• '.__('auth.notifications.login_alert.time_line', ['time' => $this->loginTime]))
            ->line('• '.__('auth.notifications.login_alert.ip_line', ['ip' => $this->ipAddress]))
            ->line('• '.__('auth.notifications.login_alert.agent_line', ['agent' => $this->userAgent]))
            ->line(__('auth.notifications.login_alert.ok_line'))
            ->line('**'.__('auth.notifications.login_alert.warn_line').'**')
            ->salutation(__('auth.notifications.common.regards')."\n".__('auth.notifications.login_alert.security_team', ['app' => $appName]));
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => __('auth.notifications.login_alert.fcm_title'),
            'body' => __('auth.notifications.login_alert.fcm_body', ['time' => $this->loginTime]),
            'data' => [
                'type' => 'security_alert',
                'ip' => $this->ipAddress,
                'agent' => $this->userAgent,
            ],
        ];
    }

    private function getName(object $notifiable): string
    {
        $nameVal = $notifiable instanceof Model
            ? $notifiable->getAttribute('name')
            : (property_exists($notifiable, 'name') ? $notifiable->name : null);

        return is_scalar($nameVal) ? (string) $nameVal : 'User';
    }
}
