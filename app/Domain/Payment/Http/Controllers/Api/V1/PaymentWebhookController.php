<?php

declare(strict_types=1);

namespace App\Domain\Payment\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Domain\Payment\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public (unauthenticated) endpoint gateways call back. Authenticity comes
 * from per-gateway signature verification inside PaymentService, never from
 * a session. Keep this route out of the tenant/idempotency middleware.
 */
class PaymentWebhookController extends ApiController
{
    public function __invoke(Request $request, string $gateway, PaymentService $payments): JsonResponse
    {
        $payment = $payments->handleWebhook($gateway, $request);

        // Gateways only need a 2xx; anything else triggers their retries.
        return $this->respond(['payment_id' => $payment->id, 'status' => $payment->status]);
    }
}
