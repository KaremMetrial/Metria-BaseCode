<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Core\Events\EventBus;
use App\Domain\Auth\Events\UserRegisteredByOtp;
use App\Domain\Auth\Models\User;
use App\Domain\Governance\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterWithOtp
{
    public function __construct(
        private readonly VerifyOtp $verifyOtp,
        private readonly EventBus $events,
        private readonly AuditLogger $audit
    ) {}

    /**
     * @return array{user: Model, token: string}
     */
    public function __invoke(array $data, string $guard = 'web', string $deviceName = 'api', ?string $tenantId = null): array
    {
        $identifierVal = $data['identifier'] ?? '';
        $identifier = is_string($identifierVal) ? $identifierVal : '';
        $codeVal = $data['code'] ?? '';
        $code = is_string($codeVal) ? $codeVal : '';

        // 1. Verify the OTP code
        $this->verifyOtp->__invoke($identifier, $code, 'register', $guard);

        // 2. Resolve the model class for the guard
        $providerVal = config("auth.guards.{$guard}.provider");
        $provider = is_string($providerVal) ? $providerVal : 'users';
        $modelClassVal = config("auth.providers.{$provider}.model");
        /** @var class-string<Model> $modelClass */
        $modelClass = is_string($modelClassVal) && class_exists($modelClassVal) ? $modelClassVal : User::class;

        // 3. Create the account inside a transaction
        return DB::transaction(function () use ($data, $identifier, $modelClass, $guard, $deviceName, $tenantId) {
            $isEmail = str_contains($identifier, '@');

            $email = $isEmail ? $identifier : ($data['email'] ?? null);
            $phone = $isEmail ? ($data['phone'] ?? null) : $identifier;

            $phoneStr = is_scalar($phone) ? (string) $phone : '';
            if (! $email && $phoneStr !== '') {
                // Generate a unique placeholder email if registering by phone and no email was provided
                $cleanPhone = (string) preg_replace('/[^0-9]/', '', $phoneStr) ?: Str::random(10);
                $email = "{$cleanPhone}@otp.local";
            }

            $tenantIdVal = $tenantId ?? ($data['tenant_id'] ?? null);
            $tenantIdToUse = is_scalar($tenantIdVal) ? (string) $tenantIdVal : null;

            $nameVal = $data['name'] ?? '';
            $nameStr = is_scalar($nameVal) ? (string) $nameVal : 'User';

            $localeVal = $data['locale'] ?? app()->getLocale();
            $localeStr = is_scalar($localeVal) ? (string) $localeVal : 'en';

            $user = $modelClass::query()->create([
                'tenant_id' => $tenantIdToUse,
                'name' => $nameStr,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make(Str::random(32)),
                'locale' => $localeStr,
            ]);

            $abilities = [];
            if ($user instanceof User) {
                $user->assignRole('customer');
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

            // Fire events
            $this->events->publish(new UserRegisteredByOtp($user, $guard));

            $userTenantId = null;
            if ($user instanceof User) {
                $userTenantId = is_scalar($user->tenant_id) ? (string) $user->tenant_id : null;
            }
            $this->audit->log('auth.registered', $user, tenantId: $userTenantId ?? $tenantIdToUse);

            return ['user' => $user, 'token' => $token];
        });
    }
}
