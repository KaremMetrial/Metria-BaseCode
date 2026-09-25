<?php

declare(strict_types=1);

namespace Modules\Integration\Infrastructure\Sms;

use Illuminate\Support\Manager;
use Modules\Integration\Domain\Contracts\SmsProvider;
use Modules\Shared\Domain\Contracts\RuntimeSettings;

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
        // Governance's runtime settings win when set (PUT
        // /governance/settings/integrations.sms.provider), so ops can
        // switch providers without a deploy; SMS_DEFAULT is the
        // env-configured fallback for a project that never touches this.
        $fromSettings = app(RuntimeSettings::class)->get('integrations.sms.provider');
        if (is_string($fromSettings) && $fromSettings !== '') {
            return $fromSettings;
        }

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
