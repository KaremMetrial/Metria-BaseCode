<?php

declare(strict_types=1);

namespace Tests\Unit\Payment;

use Modules\Payment\Infrastructure\Gateways\FawryGateway;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Fawry was flagged in the platform audit as not reviewed to the same
 * depth as Stripe/Paymob — verifyWebhook() itself was already correct
 * (hash_equals over the documented field-concatenation scheme), this just
 * closes the test coverage gap.
 */
class FawryGatewayTest extends TestCase
{
    private function gateway(string $secureKey = 'test-secure-key'): FawryGateway
    {
        return new FawryGateway(['secure_key' => $secureKey, 'merchant_code' => 'M-0001']);
    }

    /**
     * SHA256(fawryRefNumber + merchantRefNumber + paymentAmount(2dp)
     *        + orderAmount(2dp) + orderStatus + paymentMethod
     *        + paymentRefrenceNumber + secureKey)
     */
    private function signaturePayload(string $secureKey, array $overrides = []): array
    {
        $fields = array_merge([
            'fawryRefNumber' => 'FWR-9988',
            'merchantRefNumber' => 'ORD-1234',
            'paymentAmount' => 150.5,
            'orderAmount' => 150.5,
            'orderStatus' => 'PAID',
            'paymentMethod' => 'MWALLET',
            'paymentRefrenceNumber' => 'REF-001',
        ], $overrides);

        $concatenated = $fields['fawryRefNumber']
            .$fields['merchantRefNumber']
            .number_format($fields['paymentAmount'], 2, '.', '')
            .number_format($fields['orderAmount'], 2, '.', '')
            .$fields['orderStatus']
            .$fields['paymentMethod']
            .$fields['paymentRefrenceNumber']
            .$secureKey;

        $fields['messageSignature'] = hash('sha256', $concatenated);

        return $fields;
    }

    public function test_valid_signature_is_accepted(): void
    {
        $payload = $this->signaturePayload('test-secure-key');
        $request = Request::create('/webhooks/payments/fawry', 'POST', $payload);

        $this->assertTrue($this->gateway()->verifyWebhook($request));
    }

    public function test_tampered_amount_is_rejected(): void
    {
        $payload = $this->signaturePayload('test-secure-key');
        // Attacker inflates the paid amount after the signature was computed.
        $payload['paymentAmount'] = 999999.99;
        $request = Request::create('/webhooks/payments/fawry', 'POST', $payload);

        $this->assertFalse($this->gateway()->verifyWebhook($request));
    }

    public function test_signature_from_a_different_secure_key_is_rejected(): void
    {
        $payload = $this->signaturePayload('a-different-secure-key');
        $request = Request::create('/webhooks/payments/fawry', 'POST', $payload);

        $this->assertFalse($this->gateway()->verifyWebhook($request));
    }

    public function test_missing_signature_is_rejected(): void
    {
        $payload = $this->signaturePayload('test-secure-key');
        unset($payload['messageSignature']);
        $request = Request::create('/webhooks/payments/fawry', 'POST', $payload);

        $this->assertFalse($this->gateway()->verifyWebhook($request));
    }

    public function test_missing_secure_key_configuration_fails_closed(): void
    {
        $payload = $this->signaturePayload('');
        $request = Request::create('/webhooks/payments/fawry', 'POST', $payload);

        $this->assertFalse($this->gateway(secureKey: '')->verifyWebhook($request));
    }
}
