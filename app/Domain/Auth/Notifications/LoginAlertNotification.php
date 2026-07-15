<?php

declare(strict_types=1);

namespace App\Domain\Auth\Notifications;

use App\Core\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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

        $email = $notifiable instanceof \Illuminate\Database\Eloquent\Model
            ? $notifiable->getAttribute('email')
            : (property_exists($notifiable, 'email') ? $notifiable->email : null);

        if (is_string($email) && str_contains($email, '@') && ! str_ends_with($email, '@otp.local')) {
            $channels[] = 'mail';
        }

        if (method_exists($notifiable, 'fcmDeviceTokens')) {
            $tokens = $notifiable->fcmDeviceTokens();
            if ($tokens instanceof \Illuminate\Database\Eloquent\Relations\Relation && $tokens->exists()) {
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
            ->subject(__('Security Alert: New Login Detected'))
            ->greeting(__('Hello, :name', ['name' => $this->getName($notifiable)]))
            ->line(__('A new login to your account was detected.'))
            ->line(__('**Details:**'))
            ->line(__('• **Time:** :time', ['time' => $this->loginTime]))
            ->line(__('• **IP Address:** :ip', ['ip' => $this->ipAddress]))
            ->line(__('• **Device/Browser:** :agent', ['agent' => $this->userAgent]))
            ->line(__('If this login was you, no action is needed.'))
            ->line(__('**Warning: If this was NOT you, please secure your account immediately by changing your password.**'))
            ->salutation(__('Regards,')."\n".__(':app Security Team', ['app' => $appName]));
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => __('Security Alert: New Login'),
            'body' => __('A new login to your account was detected at :time.', ['time' => $this->loginTime]),
            'data' => [
                'type' => 'security_alert',
                'ip' => $this->ipAddress,
                'agent' => $this->userAgent,
            ],
        ];
    }

    private function getName(object $notifiable): string
    {
        $nameVal = $notifiable instanceof \Illuminate\Database\Eloquent\Model
            ? $notifiable->getAttribute('name')
            : (property_exists($notifiable, 'name') ? $notifiable->name : null);
        return is_scalar($nameVal) ? (string) $nameVal : 'User';
    }
}
