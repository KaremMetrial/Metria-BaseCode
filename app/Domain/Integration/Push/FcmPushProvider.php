<?php

declare(strict_types=1);

namespace App\Domain\Integration\Push;

use App\Core\Exceptions\IntegrationException;
use App\Domain\Integration\Support\CircuitBreaker;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

/**
 * Firebase Cloud Messaging — HTTP v1 API using official Kreait Firebase Admin SDK.
 * Uses OAuth2 service account authentication via config('integrations.push.fcm.credentials').
 * Docs: https://firebase.google.com/docs/cloud-messaging
 */
class FcmPushProvider
{
    public function __construct(
        protected ?Messaging $messaging = null
    ) {}

    protected function getMessaging(): Messaging
    {
        if ($this->messaging instanceof Messaging) {
            return $this->messaging;
        }

        if (app()->bound(Messaging::class)) {
            return $this->messaging = app(Messaging::class);
        }

        $credentials = config('integrations.push.fcm.credentials');
        $projectId = config('integrations.push.fcm.project_id');

        $factory = new Factory;

        if (is_string($credentials) && ! empty($credentials)) {
            // Check if it's a valid JSON string or file path
            if (str_starts_with(trim($credentials), '{')) {
                $factory = $factory->withServiceAccount(json_decode($credentials, true));
            } else {
                $factory = $factory->withServiceAccount($credentials);
            }
        }

        if (is_string($projectId) && ! empty($projectId)) {
            $factory = $factory->withProjectId($projectId);
        }

        return $this->messaging = $factory->createMessaging();
    }

    public function send(string $deviceToken, string $title, string $body, array $data = []): string
    {
        return CircuitBreaker::make()->call('fcm', function () use ($deviceToken, $title, $body, $data) {
            try {
                $message = CloudMessage::new()
                    ->toToken($deviceToken)
                    ->withNotification(Notification::create($title, $body));

                if (! empty($data)) {
                    // Ensure all data values are strings as required by FCM data payload
                    $stringData = array_map(fn ($val) => is_scalar($val) ? (string) $val : json_encode($val), $data);
                    $message = $message->withData($stringData);
                }

                $result = $this->getMessaging()->send($message);

                return is_array($result) ? ($result['name'] ?? 'sent') : (string) $result;
            } catch (FirebaseException|Throwable $e) {
                throw new IntegrationException(__('integrations.push_send_failed', ['provider' => 'fcm']), provider: 'fcm', context: [
                    'error' => $e->getMessage(),
                ], previous: $e);
            }
        });
    }
}
