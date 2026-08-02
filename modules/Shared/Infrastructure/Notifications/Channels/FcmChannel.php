<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Notifications\Channels;

// Known residual coupling: Shared depends on the concrete Auth module here
// (Auth has since fully migrated into modules/, so this is no longer blocked
// by namespace migration — it's a deliberate, disclosed trade-off). Inverting
// it cleanly means widening SendPushToUser::__invoke()'s parameter type from
// the concrete User to a shared contract exposing fcmDeviceTokens(), which is
// a real Auth-domain design change, not something to force as a side effect
// of a migration pass.
use Modules\Auth\Domain\Models\User;
use Modules\Auth\Infrastructure\Services\SendPushToUser;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function __construct(protected readonly SendPushToUser $sendPush) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User) {
            return;
        }

        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $payload = $notification->toFcm($notifiable);
        if (! is_array($payload)) {
            return;
        }

        $titleVal = $payload['title'] ?? '';
        $title = is_string($titleVal) ? $titleVal : '';

        $bodyVal = $payload['body'] ?? '';
        $body = is_string($bodyVal) ? $bodyVal : '';

        $dataVal = $payload['data'] ?? [];
        $data = is_array($dataVal) ? $dataVal : [];

        $this->sendPush->__invoke(
            $notifiable,
            $title,
            $body,
            $data
        );
    }
}
