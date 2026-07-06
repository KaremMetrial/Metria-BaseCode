<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Core\Support\Money;
use App\Domain\Governance\Models\ApprovalRequest;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Services\PaymentService;
use App\Http\Requests\CreatePaymentRequest;
use App\Http\Requests\RefundPaymentRequest;
use App\Http\Resources\ApprovalRequestResource;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends ApiController
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): JsonResponse
    {
        $payments = Payment::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(min((int) $request->query('per_page', (string) config('core.api.per_page', 20)), (int) config('core.api.max_per_page', 100)));

        return $this->respond(PaymentResource::collection($payments));
    }

    /**
     * Route carries `idempotent` middleware: send an Idempotency-Key header
     * and retries will replay the original response instead of double-charging.
     */
    public function store(CreatePaymentRequest $request): JsonResponse
    {
        $money = Money::fromDecimal(
            $request->validated('amount'),
            $request->validated('currency'),
        );

        ['payment' => $payment, 'result' => $result] = $this->payments->create(
            user: $request->user(),
            money: $money,
            gateway: $request->validated('gateway'),
            options: [
                'return_url' => $request->validated('return_url'),
                'payment_method' => $request->validated('payment_method'),
                'metadata' => $request->validated('metadata', []),
            ],
            description: $request->validated('description'),
        );

        return $this->respondCreated([
            'payment' => (new PaymentResource($payment))->resolve(),
            'next_action' => array_filter([
                'redirect_url' => $result->redirectUrl,
                'client_secret' => $result->clientSecret,
                'reference_code' => $result->referenceCode,
            ]),
        ]);
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        abort_unless(
            $payment->user_id === $request->user()->id || $request->user()->can('payments.view'),
            403,
        );

        return $this->respond(new PaymentResource($payment));
    }

    /** Maker-checker: creates an approval request (or refunds directly when approvals are off). */
    public function refund(RefundPaymentRequest $request, Payment $payment): JsonResponse
    {
        $amount = $request->validated('amount') !== null
            ? Money::fromDecimal($request->validated('amount'), $payment->currency)
            : null;

        $outcome = $this->payments->requestRefund($payment, $amount, $request->user(), $request->validated('reason'));

        if ($outcome instanceof ApprovalRequest) {
            return $this->respond(
                new ApprovalRequestResource($outcome),
                __('payments.refund_pending_approval'),
                202,
            );
        }

        return $this->respond(new PaymentResource($outcome), __('payments.refunded'));
    }
}
