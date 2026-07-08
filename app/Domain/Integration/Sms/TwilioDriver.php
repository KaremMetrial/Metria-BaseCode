<?php

declare(strict_types=1);

namespace App\Domain\Integration\Sms;

use App\Core\Exceptions\IntegrationException;
use App\Domain\Integration\Contracts\SmsProvider;
use App\Domain\Integration\Support\CircuitBreaker;
use Illuminate\Support\Facades\Http;

/**
 * Twilio Messages API (no SDK). Docs: https://www.twilio.com/docs/sms/api
 */
class TwilioDriver implements SmsProvider
{
    public function __construct(private readonly array $config) {}

    public function send(string $to, string $message): string
    {
        return CircuitBreaker::make()->call('twilio', function () use ($to, $message) {
            $sid = (string) ($this->config['sid'] ?? '');

            $response = Http::withBasicAuth($sid, (string) ($this->config['token'] ?? ''))
                ->asForm()
                ->timeout((int) config('integrations.http.timeout', 15))
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => $to,
                    'From' => (string) ($this->config['from'] ?? ''),
                    'Body' => $message,
                ]);

            if ($response->failed()) {
                throw new IntegrationException(
                    (string) $response->json('message', __('integrations.sms_send_failed', ['provider' => 'twilio'])),
                    provider: 'twilio',
                    context: ['status' => $response->status()],
                );
            }

            return (string) $response->json('sid');
        });
    }
}
