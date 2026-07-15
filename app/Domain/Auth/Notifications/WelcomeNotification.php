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

        $email = $notifiable instanceof \Illuminate\Database\Eloquent\Model
            ? $notifiable->getAttribute('email')
            : (property_exists($notifiable, 'email') ? $notifiable->email : null);

        $hasEmail = (is_string($email) && str_contains($email, '@') && ! str_ends_with($email, '@otp.local'))
            || (method_exists($notifiable, 'routeNotificationFor') && $notifiable->routeNotificationFor('mail') !== null);

        if ($hasEmail) {
            $channels[] = 'mail';
        }

        $phone = $notifiable instanceof \Illuminate\Database\Eloquent\Model
            ? $notifiable->getAttribute('phone')
            : (property_exists($notifiable, 'phone') ? $notifiable->phone : null);

        $hasPhone = (is_string($phone) && $phone !== '')
            || (method_exists($notifiable, 'routeNotificationFor') && $notifiable->routeNotificationFor('sms') !== null);

        if ($hasPhone) {
            $channels[] = SmsChannel::class;
        }

        // If the user has active FCM device tokens, send a push notification too!
        if ($notifiable instanceof \App\Domain\Auth\Models\User && $notifiable->fcmDeviceTokens()->exists()) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cfgApp = config('app.name', 'Enterprise Base');
        $appName = is_scalar($cfgApp) ? (string) $cfgApp : 'Enterprise Base';

        return (new MailMessage)
            ->subject(__('Welcome to :app!', ['app' => $appName]))
            ->greeting(__('Welcome, :name!', ['name' => $this->getName($notifiable)]))
            ->line(__('Thank you for registering. We are thrilled to have you with us.'))
            ->line(__('Your account is now active and ready. Explore the dashboard to discover all key features.'))
            ->action(__('Go to Dashboard'), url('/'))
            ->line(__('If you have any questions or need support, reply to this email.'))
            ->salutation(__('Regards,')."\n".__(':app Team', ['app' => $appName]));
    }

    public function toSms(object $notifiable): string
    {
        $cfgApp = config('app.name', 'Enterprise Base');
        $appName = is_scalar($cfgApp) ? (string) $cfgApp : 'Enterprise Base';

        return __('Welcome to :app, :name! Your account is active.', [
            'app' => $appName,
            'name' => $this->getName($notifiable),
        ]);
    }

    public function toFcm(object $notifiable): array
    {
        $cfgApp = config('app.name', 'Enterprise Base');
        $appName = is_scalar($cfgApp) ? (string) $cfgApp : 'Enterprise Base';

        return [
            'title' => __('Welcome to :app!', ['app' => $appName]),
            'body' => __('Hey :name, thanks for joining us! Your account is active.', ['name' => $this->getName($notifiable)]),
            'data' => [
                'type' => 'welcome',
                'action' => 'open_dashboard',
            ],
        ];
    }

    private function getName(object $notifiable): string
    {
        $name = $notifiable instanceof \Illuminate\Database\Eloquent\Model
            ? $notifiable->getAttribute('name')
            : (property_exists($notifiable, 'name') ? $notifiable->name : null);
        return is_string($name) ? $name : 'User';
    }
}
