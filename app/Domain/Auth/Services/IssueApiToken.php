<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Core\Exceptions\ApiException;
use App\Domain\Auth\Events\UserLoggedIn;
use App\Domain\Auth\Models\User;
use App\Domain\Governance\Services\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Service responsible for authenticating users and issuing granular Sanctum API tokens.
 * Enforces principle of least privilege: users without explicit role permissions receive empty ability scopes.
 */
class IssueApiToken
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{user: User, token: string}
     */
    public function __invoke(string $email, string $password, string $deviceName = 'api'): array
    {
        $throttleKey = 'login-attempts:'.strtolower($email);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw new ApiException(
                __('auth.throttle', ['seconds' => $seconds]),
                status: 429,
                errorCode: 'login_locked'
            );
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            RateLimiter::hit($throttleKey, 300); // 5 minutes decay

            $this->audit->log('auth.login_failed', context: ['email' => $email]);

            throw new ApiException(__('auth.failed'), status: 401, errorCode: 'invalid_credentials');
        }

        RateLimiter::clear($throttleKey);

        $abilities = $user->hasPermissionTo('admin.super')
            ? ['*']
            : $user->getPermissionsViaRoles()->pluck('name')->toArray();

        if (empty($abilities)) {
            $abilities = [];
        }

        $token = $user->createToken($deviceName, $abilities)->plainTextToken;

        $this->audit->log('auth.login', $user);

        event(new UserLoggedIn($user));

        return ['user' => $user, 'token' => $token];
    }
}
