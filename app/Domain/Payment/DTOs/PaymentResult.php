<?php

declare(strict_types=1);

namespace App\Domain\Payment\DTOs;

use App\Core\Abstracts\DataTransferObject;
use App\Domain\Payment\Enums\PaymentStatus;

/**
 * Uniform result returned by every gateway operation (create / refund).
 * Exactly one of redirectUrl / clientSecret / referenceCode is typically
 * relevant depending on how the gateway completes the payment:
 *  - redirectUrl   → hosted checkout page (Paymob iframe, PayTabs page)
 *  - clientSecret  → client-side confirmation (Stripe PaymentIntent)
 *  - referenceCode → offline code the customer pays with (Fawry cash)
 */
final class PaymentResult extends DataTransferObject
{
    public function __construct(
        public readonly bool $success,
        public readonly PaymentStatus $status,
        public readonly ?string $gatewayReference = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $clientSecret = null,
        public readonly ?string $referenceCode = null,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {}
}
