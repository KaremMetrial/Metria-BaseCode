<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\User;
use App\Domain\Integration\Push\FcmPushProvider;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Throwable;

class SendPushToUser
{
    public function __construct(private readonly FcmPushProvider $fcm) {}

    /**
     * Send push notification to all active devices of a user.
     * Automatically prunes stale/invalid device tokens.
     *
     * @return array<string, string> Map of device token => response name/id
     */
    public function __invoke(User $user, string $title, string $body, array $data = []): array
    {
        $results = [];
        $tokens = $user->fcmDeviceTokens()->get();

        foreach ($tokens as $tokenModel) {
            try {
                $results[$tokenModel->device_token] = $this->fcm->send(
                    $tokenModel->device_token,
                    $title,
                    $body,
                    $data
                );
            } catch (NotFound $e) {
                // Token is not registered with FCM anymore; delete it from our database
                $tokenModel->delete();
            } catch (Throwable $e) {
                $msg = strtolower($e->getMessage());
                if (str_contains($msg, 'notregistered') || str_contains($msg, 'not found') || str_contains($msg, 'invalid token') || str_contains($msg, 'invalid-token')) {
                    $tokenModel->delete();
                } else {
                    // Log other delivery failures without deleting the token
                    logger()->warning("FCM push delivery failed to token {$tokenModel->device_token}: ".$e->getMessage());
                }
            }
        }

        return $results;
    }
}
