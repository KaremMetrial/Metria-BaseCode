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
        $identifier = $data['identifier'];
        $code = $data['code'];

        // 1. Verify the OTP code
        $this->verifyOtp->__invoke($identifier, $code, 'register', $guard);

        // 2. Resolve the model class for the guard
        $provider = config("auth.guards.{$guard}.provider");
        $modelClass = config("auth.providers.{$provider}.model") ?? User::class;

        // 3. Create the account inside a transaction
        return DB::transaction(function () use ($data, $identifier, $modelClass, $guard, $deviceName, $tenantId) {
            $isEmail = str_contains($identifier, '@');

            $email = $isEmail ? $identifier : ($data['email'] ?? null);
            $phone = $isEmail ? ($data['phone'] ?? null) : $identifier;

            if (! $email && $phone) {
                // Generate a unique placeholder email if registering by phone and no email was provided
                $cleanPhone = preg_replace('/[^0-9]/', '', $phone) ?: Str::random(10);
                $email = "{$cleanPhone}@otp.local";
            }

            $user = $modelClass::query()->create([
                'tenant_id' => $tenantId ?? ($data['tenant_id'] ?? null),
                'name' => $data['name'],
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make(Str::random(32)),
                'locale' => $data['locale'] ?? app()->getLocale(),
            ]);

            // Assign default role if Spatie roles trait is used
            if (method_exists($user, 'assignRole')) {
                $user->assignRole('customer');
            }

            // Generate token
            $abilities = [];
            if (method_exists($user, 'hasPermissionTo') && method_exists($user, 'getPermissionsViaRoles')) {
                $abilities = $user->hasPermissionTo('admin.super')
                    ? ['*']
                    : $user->getPermissionsViaRoles()->pluck('name')->toArray();
            }

            $token = $user->createToken($deviceName, $abilities)->plainTextToken;

            // Fire events
            $this->events->publish(new UserRegisteredByOtp($user, $guard));
            $this->audit->log('auth.registered', $user, tenantId: $user->tenant_id ?? $tenantId);

            return ['user' => $user, 'token' => $token];
        });
    }
}
