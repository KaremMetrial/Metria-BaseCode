<?php

declare(strict_types=1);

namespace App\Domain\Integration\Push;

use App\Core\Exceptions\IntegrationException;
use App\Domain\Integration\Support\CircuitBreaker;
use Illuminate\Support\Facades\Http;

/**
 * Firebase Cloud Messaging — legacy HTTP endpoint for simplicity (a single
 * server key). Google is steering projects to the HTTP v1 API (OAuth2 +
 * service account); when you migrate, only this class changes.
 * Docs: https://firebase.google.com/docs/cloud-messaging
 */
class FcmPushProvider
{
    public function send(string $deviceToken, string $title, string $body, array $data = []): string
    {
        return CircuitBreaker::make()->call('fcm', function () use ($deviceToken, $title, $body, $data) {
            $response = Http::withToken((string) config('integrations.push.fcm.server_key'), 'key=')
                ->timeout((int) config('integrations.http.timeout', 15))
                ->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $deviceToken,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => $data,
                ]);

            if ($response->failed() || (int) $response->json('success', 0) < 1) {
                throw new IntegrationException('FCM push failed.', provider: 'fcm', context: [
                    'status' => $response->status(),
                    'results' => $response->json('results'),
                ]);
            }

            return (string) $response->json('multicast_id', '');
        });
    }
}
