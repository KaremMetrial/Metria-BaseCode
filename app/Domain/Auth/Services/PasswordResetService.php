<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Core\Exceptions\DomainException;
use App\Domain\Auth\Events\AllSessionsRevoked;
use App\Domain\Auth\Events\PasswordResetRequested;
use App\Domain\Auth\Events\PasswordResetSuccessfully;
use App\Domain\Auth\Models\User;
use App\Domain\Governance\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function __construct(
        private readonly AuditLogger $audit
    ) {}

    public function requestReset(string $email): void
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            // Prevent email enumeration
            return;
        }

        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        $this->audit->log('auth.password_reset_requested', $user);
        event(new PasswordResetRequested($user, $token));
    }

    public function reset(string $email, string $token, string $newPassword): User
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (! $record) {
            throw new DomainException(__('auth.recovery.invalid_token'), errorCode: 'invalid_reset_token');
        }

        $createdAt = isset($record->created_at) && (is_string($record->created_at) || $record->created_at instanceof \DateTimeInterface) ? $record->created_at : null;
        $tokenHash = isset($record->token) && is_string($record->token) ? $record->token : '';

        if ($createdAt === null || now()->subMinutes(60)->isAfter($createdAt)) {
            throw new DomainException(__('auth.recovery.invalid_token'), errorCode: 'invalid_reset_token');
        }

        if ($tokenHash === '' || ! Hash::check($token, $tokenHash)) {
            throw new DomainException(__('auth.recovery.invalid_token'), errorCode: 'invalid_reset_token');
        }

        /** @var \App\Domain\Auth\Models\User $user */
        $user = User::query()->where('email', $email)->firstOrFail();
        $user->password = Hash::make($newPassword);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // Revoke all existing tokens and sessions for security
        $user->tokens()->delete();
        $user->sessions()->delete();

        $this->audit->log('auth.password_reset_completed', $user);

        event(new AllSessionsRevoked($user));
        event(new PasswordResetSuccessfully($user));

        return $user;
    }
}
