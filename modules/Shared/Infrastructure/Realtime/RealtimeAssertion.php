<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Realtime;

/** Short-lived, audience-restricted assertion exchanged only with realtime. */
final class RealtimeAssertion
{
    /** @param array<string, mixed> $claims */
    public static function issue(array $claims): string
    {
        $secret = self::secret();
        $encoded = self::base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $encoded.'.'.hash_hmac('sha256', $encoded, $secret);
    }

    /** @return array<string, mixed>|null */
    public static function verify(string $assertion): ?array
    {
        [$encoded, $signature] = array_pad(explode('.', $assertion, 2), 2, null);
        if (! is_string($encoded) || ! is_string($signature)
            || ! hash_equals(hash_hmac('sha256', $encoded, self::secret()), $signature)) {
            return null;
        }

        try {
            $claims = json_decode(self::base64UrlDecode($encoded), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($claims)
            || ($claims['aud'] ?? null) !== 'metrial-realtime'
            || ! is_numeric($claims['exp'] ?? null)
            || (int) $claims['exp'] <= now()->timestamp) {
            return null;
        }

        return $claims;
    }

    private static function secret(): string
    {
        $secret = (string) config('realtime.internal_secret', '');
        if ($secret === '') {
            throw new \RuntimeException('REALTIME_INTERNAL_SECRET is required for realtime assertions.');
        }

        return $secret;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;

        return base64_decode(strtr($value.str_repeat('=', $padding === 0 ? 0 : 4 - $padding), '-_', '+/'), true) ?: '';
    }
}
