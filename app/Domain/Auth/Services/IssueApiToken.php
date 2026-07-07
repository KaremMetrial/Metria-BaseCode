<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Core\Exceptions\ApiException;
use App\Domain\Auth\Events\UserLoggedIn;
use App\Domain\Auth\Models\User;
use App\Domain\Governance\Services\AuditLogger;
use Illuminate\Support\Facades\Hash;

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
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            $this->audit->log('auth.login_failed', context: ['email' => $email]);

            throw new ApiException(__('auth.failed'), status: 401, errorCode: 'invalid_credentials');
        }

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
