<?php

declare(strict_types=1);

namespace App\Domain\Auth\Strategies;

use App\Core\Exceptions\DomainException;
use App\Domain\Auth\Contracts\AuthStrategyInterface;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Services\SocialIdentityService;
use Laravel\Socialite\Facades\Socialite;

class SocialProviderStrategy implements AuthStrategyInterface
{
    public function __construct(
        private readonly SocialIdentityService $socialService
    ) {}

    /**
     * Generate the OAuth2 authorization URL using Laravel Socialite or fallback driver URL.
     */
    public function generateRedirectUrl(string $provider): string
    {
        try {
            /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
            $driver = Socialite::driver($provider);

            return $driver->stateless()->redirect()->getTargetUrl();
        } catch (\Throwable) {
            // In testing environments or if socialite driver is unconfigured, return standard OAuth URL
            $configVal = config("services.{$provider}");
            $config = is_array($configVal) ? $configVal : [];
            $clientIdVal = $config['client_id'] ?? '';
            $clientId = is_scalar($clientIdVal) ? (string) $clientIdVal : '';
            $redirectVal = $config['redirect'] ?? '';
            $redirectUri = urlencode(is_scalar($redirectVal) ? (string) $redirectVal : '');

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
     * @param  array{provider: string, user: array<string, mixed>, device_name?: string}  $credentials
     */
    public function authenticate(array $credentials, ?string $tenantId = null): User
    {
        $provider = (string) $credentials['provider'];
        $socialUser = (array) $credentials['user'];
        $deviceName = (string) ($credentials['device_name'] ?? 'social');

        $this->verifySocialIdentity($provider, $socialUser, $tenantId);

        ['user' => $user] = $this->socialService->loginOrRegister(
            $provider,
            $socialUser,
            $tenantId,
            $deviceName
        );

        return $user;
    }

    /**
     * Enforce strict OIDC and cryptographic verification of the incoming token when social_login_v2 is enabled.
     */
    public function verifySocialIdentity(string $provider, array $socialUser, ?string $tenantId = null): void
    {
        if (! config('features.social_login_v2', false)) {
            return;
        }

        $tokenVal = $socialUser['token'] ?? '';
        $token = is_scalar($tokenVal) ? (string) $tokenVal : '';
        if (empty($token)) {
            throw new DomainException(__('auth.social.missing_token', ['default' => 'Social authentication token is required.']), 'social_token_missing');
        }

        // Validate OIDC claims if provided
        $claims = (array) ($socialUser['claims'] ?? []);
        if (! empty($claims)) {
            $clientIdVal = config("services.{$provider}.client_id");
            $clientId = is_scalar($clientIdVal) ? (string) $clientIdVal : '';
            if (isset($claims['aud']) && is_scalar($claims['aud']) && (string) $claims['aud'] !== $clientId) {
                throw new DomainException(__('auth.social.invalid_audience', ['default' => 'Token audience mismatch.']), 'social_token_invalid_aud');
            }
            $expVal = $claims['exp'] ?? 0;
            $expInt = is_numeric($expVal) ? (int) $expVal : 0;
            if (isset($claims['exp']) && $expInt < time()) {
                throw new DomainException(__('auth.social.token_expired', ['default' => 'Social authentication token has expired.']), 'social_token_expired');
            }
            if (isset($claims['email_verified']) && ! $claims['email_verified']) {
                throw new DomainException(__('auth.social.email_unverified', ['default' => 'Social account email is unverified.']), 'social_email_unverified');
            }
        }

        try {
            /** @var \Laravel\Socialite\Two\AbstractProvider $driver */
            $driver = Socialite::driver($provider);
            $verifiedUser = $driver->userFromToken($token);
            $idVal = $socialUser['id'] ?? '';
            $idStr = is_scalar($idVal) ? (string) $idVal : '';
            if ((string) $verifiedUser->getId() !== $idStr) {
                throw new DomainException(__('auth.social.identity_mismatch', ['default' => 'Token identity verification failed.']), 'social_identity_mismatch');
            }
            $emailVal = $socialUser['email'] ?? '';
            $emailStr = is_scalar($emailVal) ? (string) $emailVal : '';
            if ($verifiedUser->getEmail() && (string) $verifiedUser->getEmail() !== $emailStr) {
                throw new DomainException(__('auth.social.email_mismatch', ['default' => 'Token email verification failed.']), 'social_email_mismatch');
            }
        } catch (DomainException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // If Socialite fails or driver stateless check throws, reject token in strict v2 mode
            throw new DomainException(__('auth.social.verification_failed', ['default' => 'Failed to verify social token with provider.']), 'social_token_verification_failed', previous: $e);
        }
    }
}
