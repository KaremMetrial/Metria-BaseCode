<?php

declare(strict_types=1);

namespace App\Domain\Auth\Notifications;

use App\Core\Notifications\Channels\FcmChannel;
use App\Core\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        $channels = [];

        if (isset($notifiable->email) && str_contains($notifiable->email, '@') && ! str_ends_with($notifiable->email, '@otp.local')) {
            $channels[] = 'mail';
        }

        if (isset($notifiable->phone) && ! empty($notifiable->phone)) {
            $channels[] = SmsChannel::class;
        }

        // If the user has active FCM device tokens, send a push notification too!
        if (method_exists($notifiable, 'fcmDeviceTokens') && $notifiable->fcmDeviceTokens()->exists()) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name', 'Enterprise Base');

        return (new MailMessage)
            ->subject(__('Welcome to :app!', ['app' => $appName]))
            ->greeting(__('Welcome, :name!', ['name' => $notifiable->name]))
            ->line(__('Thank you for registering. We are thrilled to have you with us.'))
            ->line(__('Your account is now active and ready. Explore the dashboard to discover all key features.'))
            ->action(__('Go to Dashboard'), url('/'))
            ->line(__('If you have any questions or need support, reply to this email.'))
            ->salutation(__('Regards,')."\n".__(':app Team', ['app' => $appName]));
    }

    public function toSms(object $notifiable): string
    {
        $appName = config('app.name', 'Enterprise Base');

        return __('Welcome to :app, :name! Your account is active.', [
            'app' => $appName,
            'name' => $notifiable->name,
        ]);
    }

    public function toFcm(object $notifiable): array
    {
        $appName = config('app.name', 'Enterprise Base');

        return [
            'title' => __('Welcome to :app!', ['app' => $appName]),
            'body' => __('Hey :name, thanks for joining us! Your account is active.', ['name' => $notifiable->name]),
            'data' => [
                'type' => 'welcome',
                'action' => 'open_dashboard',
            ],
        ];
    }
}
