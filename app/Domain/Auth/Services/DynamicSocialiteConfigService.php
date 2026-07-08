<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Core\Exceptions\DomainException;
use App\Domain\Auth\Contracts\OAuthConfigurationRepositoryInterface;
use Illuminate\Support\Facades\Config;

class DynamicSocialiteConfigService
{
    public function __construct(
        private readonly OAuthConfigurationRepositoryInterface $repository
    ) {
    }

    public function configure(string $provider, ?string $tenantId = null): void
    {
        $config = $this->repository->getProviderConfig($provider, $tenantId);

        if ($config) {
            Config::set("services.{$provider}", [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect' => $config['redirect'],
            ]);
            return;
        }

        // Check if fallback exists in static services config
        $staticConfig = Config::get("services.{$provider}");
        if (! $staticConfig || empty($staticConfig['client_id'])) {
            throw new DomainException(
                __('auth.social.provider_disabled', ['provider' => $provider]),
                errorCode: 'oauth_provider_disabled'
            );
        }
    }
}
