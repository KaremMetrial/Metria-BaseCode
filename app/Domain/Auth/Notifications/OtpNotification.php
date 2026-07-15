<?php

declare(strict_types=1);

namespace App\Domain\Auth\Notifications;

use App\Core\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        // Check if notifiable has email or a mail route
        $email = $notifiable instanceof \Illuminate\Database\Eloquent\Model
            ? $notifiable->getAttribute('email')
            : (property_exists($notifiable, 'email') ? $notifiable->email : null);

        $hasEmail = (is_string($email) && str_contains($email, '@'))
            || (method_exists($notifiable, 'routeNotificationFor') && $notifiable->routeNotificationFor('mail') !== null);

        if ($hasEmail) {
            $channels[] = 'mail';
        }

        // Check if notifiable has phone or an SMS route
        $phone = $notifiable instanceof \Illuminate\Database\Eloquent\Model
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
            ->subject(__(':app OTP Verification Code', ['app' => $appName]))
            ->greeting(__('Hello!'))
            ->line(__('You are receiving this email because we received an OTP verification request for your account.'))
            ->line(__('Your verification code is:'))
            ->line("## {$this->code}")
            ->line(__('This code is valid for 10 minutes. If you did not request this, no further action is required.'))
            ->salutation(__('Regards,')."\n".__(':app Team', ['app' => $appName]));
    }

    public function toSms(object $notifiable): string
    {
        return __('Your OTP verification code is: :code. Valid for 10 minutes.', ['code' => $this->code]);
    }
}
