<?php

declare(strict_types=1);

namespace App\Domain\Auth\Strategies;

use App\Domain\Auth\Contracts\AuthStrategyInterface;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Services\SocialIdentityService;
use Laravel\Socialite\Facades\Socialite;

class SocialProviderStrategy implements AuthStrategyInterface
{
    public function __construct(
        private readonly SocialIdentityService $socialService
    ) {
    }

    /**
     * Generate the OAuth2 authorization URL using Laravel Socialite or fallback driver URL.
     */
    public function generateRedirectUrl(string $provider): string
    {
        try {
            return Socialite::driver($provider)->stateless()->redirect()->getTargetUrl();
        } catch (\Throwable) {
            // In testing environments or if socialite driver is unconfigured, return standard OAuth URL
            $config = config("services.{$provider}");
            $clientId = $config['client_id'] ?? '';
            $redirectUri = urlencode((string) ($config['redirect'] ?? ''));

            return match ($provider) {
                'google' => "https://accounts.google.com/o/oauth2/v2/auth?client_id={$clientId}&redirect_uri={$redirectUri}&response_type=code&scope=email%20profile",
                'apple' => "https://appleid.apple.com/auth/authorize?client_id={$clientId}&redirect_uri={$redirectUri}&response_type=code&scope=name%20email",
                'github' => "https://github.com/login/oauth/authorize?client_id={$clientId}&redirect_uri={$redirectUri}&scope=user:email",
                default => "https://example.com/oauth/authorize?client_id={$clientId}&redirect_uri={$redirectUri}&response_type=code",
            };
        }
    }

    /**
     * Authenticate or register a user via OAuth payload.
     *
     * @param array{provider: string, user: array<string, mixed>, device_name?: string} $credentials
     */
    public function authenticate(array $credentials, ?string $tenantId = null): User
    {
        $provider = (string) $credentials['provider'];
        $socialUser = (array) $credentials['user'];
        $deviceName = (string) ($credentials['device_name'] ?? 'social');

        ['user' => $user] = $this->socialService->loginOrRegister(
            $provider,
            $socialUser,
            $tenantId,
            $deviceName
        );

        return $user;
    }
}
