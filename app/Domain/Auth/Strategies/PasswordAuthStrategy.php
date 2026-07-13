<?php

declare(strict_types=1);

namespace App\Domain\Auth\Strategies;

use App\Core\Exceptions\ApiException;
use App\Domain\Auth\Contracts\AuthStrategyInterface;
use App\Domain\Auth\Models\User;
use App\Domain\Governance\Services\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class PasswordAuthStrategy implements AuthStrategyInterface
{
    public function __construct(
        private readonly AuditLogger $audit
    ) {}

    /**
     * Authenticate a user by checking email and password credentials.
     *
     * @param  array{email: string, password: string}  $credentials
     */
    public function authenticate(array $credentials, ?string $tenantId = null): User
    {
        $email = (string) $credentials['email'];
        $password = (string) $credentials['password'];
        $throttleKey = 'login-attempts:'.strtolower($email);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw new ApiException(
                __('auth.throttle', ['seconds' => $seconds]),
                status: 429,
                errorCode: 'login_locked'
            );
        }

        /** @var User|null $user */
        $user = User::query()
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->where('email', $email)
            ->first();

        if (! $user || ! Hash::check($password, (string) $user->password)) {
            RateLimiter::hit($throttleKey, 300); // 5 minutes decay
            $this->audit->log('auth.login_failed', context: ['email' => $email]);

            throw new ApiException(__('auth.failed'), status: 401, errorCode: 'invalid_credentials');
        }

        RateLimiter::clear($throttleKey);

        return $user;
    }
}
