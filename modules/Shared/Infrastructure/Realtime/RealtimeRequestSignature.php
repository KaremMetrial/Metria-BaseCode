<?php

declare(strict_types=1);

namespace Modules\Shared\Infrastructure\Realtime;

/** Canonical HMAC contract for Node-to-Laravel internal requests. */
final class RealtimeRequestSignature
{
    public static function canonical(string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        $normalizedMethod = strtoupper(trim($method));
        $normalizedPath = '/'.ltrim($path, '/');

        if (! preg_match('/^[A-Z]+$/', $normalizedMethod)
            || str_contains($normalizedPath, "\n")
            || str_contains($normalizedPath, "\r")) {
            throw new \InvalidArgumentException('Invalid realtime request signature fields.');
        }

        return implode("\n", [$normalizedMethod, $normalizedPath, $timestamp, $nonce, hash('sha256', $body)]);
    }

    public static function sign(string $method, string $path, string $timestamp, string $nonce, string $body, string $secret): string
    {
        return hash_hmac('sha256', self::canonical($method, $path, $timestamp, $nonce, $body), $secret);
    }
}
