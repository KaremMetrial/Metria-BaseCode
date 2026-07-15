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
        $result = CircuitBreaker::make()->call('twilio', function () use ($to, $message) {
            $sidVal = $this->config['sid'] ?? '';
            $sid = is_scalar($sidVal) ? (string) $sidVal : '';
            $timeoutVal = config('integrations.http.timeout', 15);
            $timeout = is_numeric($timeoutVal) ? (int) $timeoutVal : 15;
            $tokenVal = $this->config['token'] ?? '';
            $token = is_scalar($tokenVal) ? (string) $tokenVal : '';
            $fromVal = $this->config['from'] ?? '';
            $from = is_scalar($fromVal) ? (string) $fromVal : '';

            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->timeout($timeout)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => $to,
                    'From' => $from,
                    'Body' => $message,
                ]);

            if ($response->failed()) {
                $msgVal = $response->json('message');
                $msg = is_string($msgVal) ? $msgVal : __('integrations.sms_send_failed', ['provider' => 'twilio']);
                throw new IntegrationException(
                    $msg,
                    provider: 'twilio',
                    context: ['status' => $response->status()],
                );
            }

            $sidVal = $response->json('sid');
            return is_string($sidVal) ? $sidVal : '';
        });

        return is_string($result) ? $result : '';
    }
}
