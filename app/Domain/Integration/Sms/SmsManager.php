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
        $default = $this->config->get('integrations.sms.default', 'log');
        return is_string($default) ? $default : 'log';
    }

    protected function createTwilioDriver(): SmsProvider
    {
        $config = $this->config->get('integrations.sms.twilio', []);
        return new TwilioDriver(is_array($config) ? $config : []);
    }

    protected function createVonageDriver(): SmsProvider
    {
        $config = $this->config->get('integrations.sms.vonage', []);
        return new VonageDriver(is_array($config) ? $config : []);
    }

    protected function createLogDriver(): SmsProvider
    {
        return new LogDriver;
    }
}
