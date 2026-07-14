<?php

declare(strict_types=1);

namespace App\Domain\Integration\Sms;

use App\Core\Exceptions\IntegrationException;
use App\Domain\Integration\Contracts\SmsProvider;
use App\Domain\Integration\Support\CircuitBreaker;
use Illuminate\Support\Facades\Http;

/**
 * Vonage (Nexmo) SMS API. Docs: https://developer.vonage.com/en/messaging/sms
 */
class VonageDriver implements SmsProvider
{
    public function __construct(private readonly array $config) {}

    public function send(string $to, string $message): string
    {
        $result = CircuitBreaker::make()->call('vonage', function () use ($to, $message) {
            $timeoutVal = config('integrations.http.timeout', 15);
            $timeout = is_numeric($timeoutVal) ? (int) $timeoutVal : 15;

            $appNameVal = config('app.name');
            $appName = is_string($appNameVal) ? $appNameVal : 'Laravel';

            $response = Http::asForm()
                ->timeout($timeout)
                ->post('https://rest.nexmo.com/sms/json', [
                    'api_key' => (string) ($this->config['key'] ?? ''),
                    'api_secret' => (string) ($this->config['secret'] ?? ''),
                    'from' => (string) ($this->config['from'] ?? $appName),
                    'to' => ltrim($to, '+'),
                    'text' => $message,
                    'type' => 'unicode', // Arabic-safe
                ]);

            $statusVal = data_get($response->json(), 'messages.0.status', '');
            $status = is_scalar($statusVal) ? (string) $statusVal : '';

            if ($response->failed() || $status !== '0') {
                $errTextVal = data_get($response->json(), 'messages.0.error-text');
                $errText = is_string($errTextVal) ? $errTextVal : __('integrations.sms_send_failed', ['provider' => 'vonage']);
                throw new IntegrationException(
                    $errText,
                    provider: 'vonage',
                    context: ['status' => $response->status(), 'provider_status' => $status],
                );
            }

            $msgIdVal = data_get($response->json(), 'messages.0.message-id', '');
            return is_scalar($msgIdVal) ? (string) $msgIdVal : '';
        });

        return is_string($result) ? $result : '';
    }
}
