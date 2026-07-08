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
        return CircuitBreaker::make()->call('vonage', function () use ($to, $message) {
            $response = Http::asForm()
                ->timeout((int) config('integrations.http.timeout', 15))
                ->post('https://rest.nexmo.com/sms/json', [
                    'api_key' => (string) ($this->config['key'] ?? ''),
                    'api_secret' => (string) ($this->config['secret'] ?? ''),
                    'from' => (string) ($this->config['from'] ?? config('app.name')),
                    'to' => ltrim($to, '+'),
                    'text' => $message,
                    'type' => 'unicode', // Arabic-safe
                ]);

            $status = (string) data_get($response->json(), 'messages.0.status', '');

            if ($response->failed() || $status !== '0') {
                throw new IntegrationException(
                    (string) data_get($response->json(), 'messages.0.error-text', __('integrations.sms_send_failed', ['provider' => 'vonage'])),
                    provider: 'vonage',
                    context: ['status' => $response->status(), 'provider_status' => $status],
                );
            }

            return (string) data_get($response->json(), 'messages.0.message-id', '');
        });
    }
}
