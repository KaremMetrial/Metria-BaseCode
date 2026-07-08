<?php

declare(strict_types=1);

namespace App\Domain\Auth\Strategies;

use App\Core\Exceptions\ApiException;
use App\Domain\Auth\Contracts\AuthStrategyInterface;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\Services\VerifyOtp;

class OtpAuthStrategy implements AuthStrategyInterface
{
    public function __construct(
        private readonly VerifyOtp $verifyOtp
    ) {
    }

    /**
     * Authenticate or resolve a user via OTP verification.
     *
     * @param array{identifier: string, code: string, guard?: string} $credentials
     */
    public function authenticate(array $credentials, ?string $tenantId = null): User
    {
        $identifier = (string) $credentials['identifier'];
        $code = (string) $credentials['code'];
        $guard = (string) ($credentials['guard'] ?? 'web');

        $this->verifyOtp->__invoke($identifier, $code, 'login', $guard);

        $provider = config("auth.guards.{$guard}.provider");
        $modelClass = config("auth.providers.{$provider}.model") ?? User::class;

        /** @var User|null $user */
        $user = $modelClass::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where(function ($q) use ($identifier) {
                $q->where('email', $identifier)
                    ->orWhere('phone', $identifier);
            })
            ->first();

        if (! $user) {
            throw new ApiException(__('auth.user_not_found', ['default' => 'No user account found with this identifier.']), status: 404, errorCode: 'user_not_found');
        }

        return $user;
    }
}
