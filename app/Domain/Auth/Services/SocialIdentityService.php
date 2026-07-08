<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Core\Exceptions\ApiException;
use App\Core\Exceptions\DomainException;
use App\Domain\Auth\Events\SocialIdentityLinked;
use App\Domain\Auth\Events\SocialIdentityUnlinked;
use App\Domain\Auth\Events\UserLoggedInByProvider;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Models\UserSocialIdentity;
use App\Domain\Governance\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SocialIdentityService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AuthMethodGovernanceService $governance
    ) {
    }

    /**
     * @return array{user: User, token: string, is_new: bool}
     */
    public function loginOrRegister(string $provider, array $socialUser, ?string $tenantId = null, string $deviceName = 'social'): array
    {
        $this->governance->checkMethodEnabled('social');

        return DB::transaction(function () use ($provider, $socialUser, $tenantId, $deviceName) {
            /** @var UserSocialIdentity|null $identity */
            $identity = UserSocialIdentity::query()
                ->where('provider', $provider)
                ->where('provider_user_id', (string) $socialUser['id'])
                ->first();

            $isNew = false;
            $user = null;

            if ($identity) {
                $user = $identity->user;
                $identity->update([
                    'provider_email' => $socialUser['email'] ?? null,
                    'access_token' => $socialUser['token'] ?? null,
                    'refresh_token' => $socialUser['refreshToken'] ?? null,
                    'expires_at' => isset($socialUser['expiresIn']) ? now()->addSeconds((int) $socialUser['expiresIn']) : null,
                ]);
            } else {
                // Check if user exists by email
                $email = $socialUser['email'] ?? null;
                if ($email) {
                    $user = User::query()->where('email', $email)->first();
                }

                if (! $user) {
                    $isNew = true;
                    $user = User::query()->create([
                        'tenant_id' => $tenantId,
                        'name' => $socialUser['name'] ?? 'User',
                        'email' => $email ?? "{$provider}_".Str::random(10)."@example.com",
                        'password' => null,
                        'email_verified_at' => now(),
                    ]);
                }

                UserSocialIdentity::query()->create([
                    'user_id' => $user->id,
                    'provider' => $provider,
                    'provider_user_id' => (string) $socialUser['id'],
                    'provider_email' => $email,
                    'access_token' => $socialUser['token'] ?? null,
                    'refresh_token' => $socialUser['refreshToken'] ?? null,
                    'expires_at' => isset($socialUser['expiresIn']) ? now()->addSeconds((int) $socialUser['expiresIn']) : null,
                ]);

                event(new SocialIdentityLinked($user, $provider));
            }

            $abilities = $user->hasPermissionTo('admin.super')
                ? ['*']
                : $user->getPermissionsViaRoles()->pluck('name')->toArray();

            if (empty($abilities)) {
                $abilities = [];
            }

            $token = $user->createToken($deviceName, $abilities)->plainTextToken;

            $this->audit->log('auth.social_login', $user, ['provider' => $provider]);

            event(new UserLoggedInByProvider($user, $provider));

            return ['user' => $user, 'token' => $token, 'is_new' => $isNew];
        });
    }

    public function linkIdentity(User $user, string $provider, array $socialUser): UserSocialIdentity
    {
        $existing = UserSocialIdentity::query()
            ->where('provider', $provider)
            ->where('provider_user_id', (string) $socialUser['id'])
            ->first();

        if ($existing) {
            if ($existing->user_id === $user->id) {
                return $existing;
            }
            throw new DomainException(__('auth.social.conflict'), errorCode: 'social_identity_conflict');
        }

        $identity = UserSocialIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_user_id' => (string) $socialUser['id'],
            'provider_email' => $socialUser['email'] ?? null,
            'access_token' => $socialUser['token'] ?? null,
            'refresh_token' => $socialUser['refreshToken'] ?? null,
            'expires_at' => isset($socialUser['expiresIn']) ? now()->addSeconds((int) $socialUser['expiresIn']) : null,
        ]);

        $this->audit->log('auth.social_linked', $user, ['provider' => $provider]);

        event(new SocialIdentityLinked($user, $provider));

        return $identity;
    }

    public function unlinkIdentity(User $user, string $provider): void
    {
        if (! $user->canUnlinkIdentity()) {
            throw new DomainException(
                __('auth.social.cannot_unlink_only'),
                errorCode: 'cannot_unlink_only_identity'
            );
        }

        $deleted = $user->socialIdentities()->where('provider', $provider)->delete();

        if ($deleted) {
            $this->audit->log('auth.social_unlinked', $user, ['provider' => $provider]);
            event(new SocialIdentityUnlinked($user, $provider));
        }
    }
}
