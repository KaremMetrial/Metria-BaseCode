<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Core\Events\EventBus;
use App\Core\Exceptions\ApiException;
use App\Domain\Auth\Events\UserLoggedInByOtp;
use App\Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Model;

class LoginWithOtp
{
    public function __construct(
        private readonly VerifyOtp $verifyOtp,
        private readonly EventBus $events
    ) {}

    /**
     * @return array{user: Model, token: string}
     */
    public function __invoke(string $identifier, string $code, string $guard = 'web', string $deviceName = 'api'): array
    {
        // 1. Verify the OTP code
        $this->verifyOtp->__invoke($identifier, $code, 'login', $guard);

        // 2. Resolve the model class for the guard
        $providerVal = config("auth.guards.{$guard}.provider");
        $provider = is_string($providerVal) ? $providerVal : 'users';
        $modelClassVal = config("auth.providers.{$provider}.model");
        /** @var class-string<Model> $modelClass */
        $modelClass = is_string($modelClassVal) && class_exists($modelClassVal) ? $modelClassVal : User::class;

        // 3. Find the user by phone or email (scoped correctly within tenant bounds)
        $user = $modelClass::query()
            ->where(function ($q) use ($identifier) {
                $q->where('email', $identifier)
                    ->orWhere('phone', $identifier);
            })
            ->first();

        if (! $user) {
            throw new ApiException(__('auth.user_not_found', ['default' => 'No user account found with this identifier.']), status: 404, errorCode: 'user_not_found');
        }

        // 4. Generate abilities & Sanctum token
        $abilities = [];
        if ($user instanceof User) {
            $abilities = $user->hasPermissionTo('admin.super')
                ? ['*']
                : $user->getPermissionsViaRoles()->pluck('name')->toArray();
        }

        $token = '';
        if (method_exists($user, 'createToken')) {
            /** @var \Laravel\Sanctum\NewAccessToken $newToken */
            $newToken = $user->createToken($deviceName, $abilities);
            $token = $newToken->plainTextToken;
        }

        // 5. Fire Login event
        $this->events->publish(new UserLoggedInByOtp($user, $guard));

        return ['user' => $user, 'token' => $token];
    }
}
