<?php

declare(strict_types=1);

namespace Tests\Unit\Payment;

use Modules\Payment\Infrastructure\Gateways\PaytabsGateway;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * PayTabs was flagged in the platform audit as not reviewed to the same
 * depth as Stripe/Paymob — verifyWebhook() itself was already correct
 * (hash_equals, HMAC-SHA256 over the raw body), this just closes the test
 * coverage gap.
 */
class PaytabsGatewayTest extends TestCase
{
    private function gateway(string $serverKey = 'test-server-key'): PaytabsGateway
    {
        return new PaytabsGateway(['server_key' => $serverKey, 'profile_id' => 12345]);
    }

    public function test_valid_signature_is_accepted(): void
    {
        $body = '{"tran_ref":"TST123","cart_id":"abc"}';
        $signature = hash_hmac('sha256', $body, 'test-server-key');

        $request = Request::create('/webhooks/payments/paytabs', 'POST', [], [], [], [
            'HTTP_Signature' => $signature,
        ], $body);

        $this->assertTrue($this->gateway()->verifyWebhook($request));
    }

    public function test_tampered_body_is_rejected(): void
    {
        $originalBody = '{"tran_ref":"TST123","cart_id":"abc"}';
        $signature = hash_hmac('sha256', $originalBody, 'test-server-key');

        $tamperedBody = '{"tran_ref":"TST123","cart_id":"attacker-controlled"}';
        $request = Request::create('/webhooks/payments/paytabs', 'POST', [], [], [], [
            'HTTP_Signature' => $signature,
        ], $tamperedBody);

        $this->assertFalse($this->gateway()->verifyWebhook($request));
    }

    public function test_signature_from_a_different_server_key_is_rejected(): void
    {
        $body = '{"tran_ref":"TST123"}';
        $signature = hash_hmac('sha256', $body, 'a-different-server-key');

        $request = Request::create('/webhooks/payments/paytabs', 'POST', [], [], [], [
            'HTTP_Signature' => $signature,
        ], $body);

        $this->assertFalse($this->gateway()->verifyWebhook($request));
    }

    public function test_missing_signature_header_is_rejected(): void
    {
        $request = Request::create('/webhooks/payments/paytabs', 'POST', [], [], [], [], '{"tran_ref":"TST123"}');

        $this->assertFalse($this->gateway()->verifyWebhook($request));
    }

    public function test_missing_server_key_configuration_fails_closed(): void
    {
        $body = '{"tran_ref":"TST123"}';
        $signature = hash_hmac('sha256', $body, '');

        $request = Request::create('/webhooks/payments/paytabs', 'POST', [], [], [], [
            'HTTP_Signature' => $signature,
        ], $body);

        // An unconfigured gateway must never accept webhooks just because
        // both sides compute the same hash over an empty key.
        $this->assertFalse($this->gateway(serverKey: '')->verifyWebhook($request));
    }
}
