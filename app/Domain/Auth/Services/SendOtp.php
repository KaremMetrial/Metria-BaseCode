<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Core\Events\EventBus;
use App\Domain\Auth\Events\OtpGenerated;
use App\Domain\Auth\Models\OtpCode;
use Illuminate\Support\Facades\DB;

class SendOtp
{
    public function __construct(private readonly EventBus $events) {}

    public function __invoke(string $identifier, string $action, string $guard = 'web'): OtpCode
    {
        return DB::transaction(function () use ($identifier, $action, $guard) {
            // Deactivate any existing active OTP codes for this identifier, guard, and action
            OtpCode::query()
                ->where('identifier', $identifier)
                ->where('guard', $guard)
                ->where('action', $action)
                ->active()
                ->update(['expires_at' => now()]);

            // Generate a random 6-digit numeric OTP code, or use a fixed code for local/testing
            $code = app()->environment('local', 'testing')
                ? '123456'
                : (string) random_int(100000, 999999);

            // Create a new OTP code valid for 10 minutes
            $otp = OtpCode::query()->create([
                'identifier' => $identifier,
                'code' => $code,
                'guard' => $guard,
                'action' => $action,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10),
            ]);

            // Publish the OtpGenerated event
            $this->events->publish(new OtpGenerated($identifier, $code, $action, $guard));

            return $otp;
        });
    }
}
