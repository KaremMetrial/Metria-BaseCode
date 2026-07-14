<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Core\Events\EventBus;
use App\Core\Exceptions\ApiException;
use App\Domain\Auth\Events\OtpFailed;
use App\Domain\Auth\Events\OtpVerified;
use App\Domain\Auth\Models\OtpCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class VerifyOtp
{
    private const int MAX_ATTEMPTS = 5;

    public function __construct(private readonly EventBus $events) {}

    public function __invoke(string $identifier, string $code, string $action, string $guard = 'web'): bool
    {
        $rateLimitKey = "otp:verify:{$identifier}:{$action}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $this->events->publish(new OtpFailed($identifier, $action, $guard, 'rate_limit_exceeded'));
            throw new ApiException(__('auth.otp_rate_limited', ['default' => 'Too many verification attempts. Please try again later.']), status: 429, errorCode: 'otp_rate_limited');
        }
        RateLimiter::hit($rateLimitKey, 60);

        $lockKey = "otp:lock:{$identifier}:{$action}";
        $lock = Cache::lock($lockKey, 5);

        if (! $lock->get()) {
            throw new ApiException(__('auth.otp_locked', ['default' => 'Verification in progress. Please wait a moment.']), status: 429, errorCode: 'otp_locked');
        }

        try {
            $error = DB::transaction(function () use ($identifier, $code, $action, $guard, $rateLimitKey) {
                /** @var OtpCode|null $otp */
                $otp = OtpCode::query()
                    ->where('identifier', $identifier)
                    ->where('guard', $guard)
                    ->where('action', $action)
                    ->active()
                    ->lockForUpdate()
                    ->first();

                if (! $otp) {
                    $this->events->publish(new OtpFailed($identifier, $action, $guard, 'not_found_or_expired'));
                    return ['status' => 422, 'code' => 'otp_expired', 'message' => __('auth.otp_not_found_or_expired', ['default' => 'OTP code is invalid or has expired.'])];
                }

                if ($otp->attempts >= self::MAX_ATTEMPTS) {
                    $otp->update(['expires_at' => now()]);
                    $this->events->publish(new OtpFailed($identifier, $action, $guard, 'max_attempts_exceeded'));
                    return ['status' => 422, 'code' => 'otp_max_attempts', 'message' => __('auth.otp_max_attempts', ['default' => 'Too many invalid attempts. Please request a new OTP.'])];
                }

                if ($otp->code !== $code) {
                    $otp->increment('attempts');
                    $this->events->publish(new OtpFailed($identifier, $action, $guard, 'invalid_code'));
                    return ['status' => 422, 'code' => 'otp_invalid', 'message' => __('auth.otp_invalid', ['default' => 'Invalid OTP code.'])];
                }

                $otp->update(['verified_at' => now()]);
                RateLimiter::clear($rateLimitKey);
                $this->events->publish(new OtpVerified($identifier, $action, $guard));

                return null;
            });

            if ($error !== null) {
                throw new ApiException($error['message'], status: $error['status'], errorCode: $error['code']);
            }

            return true;
        } finally {
            $lock->release();
        }
    }
}
