<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Core\Events\EventBus;
use App\Core\Exceptions\ApiException;
use App\Domain\Auth\Events\OtpFailed;
use App\Domain\Auth\Events\OtpVerified;
use App\Domain\Auth\Models\OtpCode;
use Illuminate\Support\Facades\DB;

class VerifyOtp
{
    private const int MAX_ATTEMPTS = 5;

    public function __construct(private readonly EventBus $events) {}

    public function __invoke(string $identifier, string $code, string $action, string $guard = 'web'): bool
    {
        $otp = OtpCode::query()
            ->where('identifier', $identifier)
            ->where('guard', $guard)
            ->where('action', $action)
            ->active()
            ->first();

        if (! $otp) {
            $this->events->publish(new OtpFailed($identifier, $action, $guard, 'not_found_or_expired'));
            throw new ApiException(__('auth.otp_not_found_or_expired', ['default' => 'OTP code is invalid or has expired.']), status: 422, errorCode: 'otp_expired');
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            $otp->update(['expires_at' => now()]);
            $this->events->publish(new OtpFailed($identifier, $action, $guard, 'max_attempts_exceeded'));
            throw new ApiException(__('auth.otp_max_attempts', ['default' => 'Too many invalid attempts. Please request a new OTP.']), status: 422, errorCode: 'otp_max_attempts');
        }

        if ($otp->code !== $code) {
            $otp->increment('attempts');
            $this->events->publish(new OtpFailed($identifier, $action, $guard, 'invalid_code'));
            throw new ApiException(__('auth.otp_invalid', ['default' => 'Invalid OTP code.']), status: 422, errorCode: 'otp_invalid');
        }

        // OTP verified successfully
        return DB::transaction(function () use ($otp, $identifier, $action, $guard) {
            $otp->update(['verified_at' => now()]);
            $this->events->publish(new OtpVerified($identifier, $action, $guard));

            return true;
        });
    }
}
