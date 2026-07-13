<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Core\Exceptions\DomainException;
use App\Domain\Auth\Events\MfaDisabled;
use App\Domain\Auth\Events\MfaEnabled;
use App\Domain\Auth\Events\MfaVerified;
use App\Domain\Auth\Models\User;
use App\Domain\Governance\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MfaService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{secret: string, qr_url: string, recovery_codes: string[]}
     */
    public function enable(User $user): array
    {
        $secret = $this->generateBase32Secret(32);
        $rawCodes = $this->generateRecoveryCodes(8);
        $hashedCodes = array_map(fn ($code) => Hash::make($code), $rawCodes);

        $user->two_factor_secret = $secret;
        $user->two_factor_recovery_codes = json_encode($hashedCodes);
        $user->two_factor_confirmed_at = null; // Needs confirmation
        $user->save();

        $appName = urlencode((string) config('app.name', 'Laravel'));
        $userEmail = urlencode($user->email);
        $qrUrl = "otpauth://totp/{$appName}:{$userEmail}?secret={$secret}&issuer={$appName}";

        return [
            'secret' => $secret,
            'qr_url' => $qrUrl,
            'recovery_codes' => $rawCodes,
        ];
    }

    public function confirm(User $user, string $code): bool
    {
        if (empty($user->two_factor_secret)) {
            throw new DomainException(__('auth.mfa.not_initialized'), errorCode: 'mfa_not_initialized');
        }

        if (! $this->verifyTotp($user->two_factor_secret, $code)) {
            return false;
        }

        $user->two_factor_confirmed_at = now();
        $user->save();

        $this->audit->log('auth.mfa_enabled', $user);
        event(new MfaEnabled($user));

        return true;
    }

    public function verify(User $user, string $code): bool
    {
        if (! $user->hasMfaEnabled()) {
            return true;
        }

        $cacheKey = "mfa_used_code:{$user->id}:{$code}";

        if (Cache::has($cacheKey)) {
            return false; // Replay-attack protection: same code already consumed
        }

        if ($this->verifyTotp($user->two_factor_secret, $code)) {
            Cache::put($cacheKey, true, 60);
            $this->audit->log('auth.mfa_verified', $user);
            event(new MfaVerified($user));

            return true;
        }

        // Try recovery codes
        if ($this->consumeRecoveryCode($user, $code)) {
            $this->audit->log('auth.mfa_recovery_used', $user);
            event(new MfaVerified($user));

            return true;
        }

        return false;
    }

    public function disable(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw new DomainException(__('auth.mfa.invalid_password'), errorCode: 'invalid_password');
        }

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        $this->audit->log('auth.mfa_disabled', $user);
        event(new MfaDisabled($user));
    }

    private function generateBase32Secret(int $length): string
    {
        $secret = '';
        $alphabet = self::BASE32_ALPHABET;
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }

        return $secret;
    }

    private function generateRecoveryCodes(int $count): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::upper(Str::random(5)).'-'.Str::upper(Str::random(5));
        }

        return $codes;
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        if (empty($user->two_factor_recovery_codes)) {
            return false;
        }

        $hashedCodes = json_decode((string) $user->two_factor_recovery_codes, true) ?? [];
        foreach ($hashedCodes as $index => $hash) {
            if (Hash::check($code, $hash)) {
                unset($hashedCodes[$index]);
                $user->two_factor_recovery_codes = json_encode(array_values($hashedCodes));
                $user->save();

                return true;
            }
        }

        return false;
    }

    public function verifyTotp(string $secret, string $code): bool
    {

        $decoded = $this->base32Decode($secret);
        if ($decoded === '') {
            return false;
        }

        $currentTimeSlice = (int) floor(time() / 30);

        // Allow ±1 time slice (±30s drift)
        for ($offset = -1; $offset <= 1; $offset++) {
            $timeSlice = $currentTimeSlice + $offset;
            $binaryTimeSlice = pack('N', 0).pack('N', $timeSlice);
            $hmac = hash_hmac('sha1', $binaryTimeSlice, $decoded, true);
            $offsetIndex = ord($hmac[19]) & 0xF;
            $value = ((ord($hmac[$offsetIndex]) & 0x7F) << 24)
                | ((ord($hmac[$offsetIndex + 1]) & 0xFF) << 16)
                | ((ord($hmac[$offsetIndex + 2]) & 0xFF) << 8)
                | (ord($hmac[$offsetIndex + 3]) & 0xFF);
            $otp = str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);

            if (hash_equals($otp, $code)) {
                return true;
            }
        }

        return false;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper($secret);
        $alphabet = self::BASE32_ALPHABET;
        $bits = '';
        for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
            $char = $secret[$i];
            $val = strpos($alphabet, $char);
            if ($val === false) {
                continue;
            }
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        for ($i = 0, $len = strlen($bits); $i + 8 <= $len; $i += 8) {
            $bytes .= chr((int) bindec(substr($bits, $i, 8)));
        }

        return $bytes;
    }
}
