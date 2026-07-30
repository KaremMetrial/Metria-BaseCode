<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Notifications\Channels;

// TODO(IAM migration, Phase 6): SendPushToUser::__invoke() requires a concrete
// App\Domain\Auth\Models\User because it calls $user->fcmDeviceTokens(). Inverting
// this cleanly means widening that method's parameter type to a shared contract,
// which is Auth-domain code out of scope for this session — deferred to IAM's own
// migration rather than edited here as a side effect of the Shared Kernel move.
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Services\SendPushToUser;
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
