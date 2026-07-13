<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /** Seed roles/permissions so registration + RBAC work in every test. */
    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Generate a valid TOTP code from a Base32 secret for the current time window.
     * Use this in tests instead of the removed '000000' backdoor.
     */
    protected function generateTotp(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);

        // Base32 decode
        $bits = '';
        for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
            $val = strpos($alphabet, $secret[$i]);
            if ($val === false) {
                continue;
            }
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }
        $key = '';
        for ($i = 0, $len = strlen($bits); $i + 8 <= $len; $i += 8) {
            $key .= chr((int) bindec(substr($bits, $i, 8)));
        }

        $timeSlice = (int) floor(time() / 30);
        $binaryTime = pack('N', 0).pack('N', $timeSlice);
        $hmac = hash_hmac('sha1', $binaryTime, $key, true);
        $offset = ord($hmac[19]) & 0xF;
        $value = ((ord($hmac[$offset]) & 0x7F) << 24)
            | ((ord($hmac[$offset + 1]) & 0xFF) << 16)
            | ((ord($hmac[$offset + 2]) & 0xFF) << 8)
            | (ord($hmac[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }
}
