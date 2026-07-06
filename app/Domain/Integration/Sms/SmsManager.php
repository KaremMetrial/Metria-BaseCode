<?php

declare(strict_types=1);

namespace App\Domain\Integration\Sms;

use App\Domain\Integration\Contracts\SmsProvider;
use Illuminate\Support\Manager;

/**
 * Same Manager/Driver pattern as payments — swap providers per environment
 * with SMS_DEFAULT, or resolve one explicitly:
 *
 *   app(SmsManager::class)->driver('twilio')->send($to, $text);
 *
 * @method SmsProvider driver(string|null $driver = null)
 */
class SmsManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('integrations.sms.default', 'log');
    }

    protected function createTwilioDriver(): SmsProvider
    {
        return new TwilioDriver($this->config->get('integrations.sms.twilio', []));
    }

    protected function createVonageDriver(): SmsProvider
    {
        return new VonageDriver($this->config->get('integrations.sms.vonage', []));
    }

    protected function createLogDriver(): SmsProvider
    {
        return new LogDriver;
    }
}
