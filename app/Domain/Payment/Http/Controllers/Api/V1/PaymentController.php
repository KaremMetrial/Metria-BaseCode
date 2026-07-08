<?php

declare(strict_types=1);

namespace App\Domain\Payment\Http\Controllers\Api\V1;

use App\Core\Http\Controllers\ApiController;
use App\Core\Support\Money;
use App\Domain\Governance\Http\Resources\ApprovalRequestResource;
use App\Domain\Governance\Models\ApprovalRequest;
use App\Domain\Payment\Http\Requests\CreatePaymentRequest;
use App\Domain\Payment\Http\Requests\RefundPaymentRequest;
use App\Domain\Payment\Http\Resources\PaymentResource;
use App\Domain\Payment\Models\Payment;
use App\Domain\Payment\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentController extends ApiController
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Payment::class);
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
        Gate::authorize('view', $payment);

        return $this->respond(new PaymentResource($payment));
    }

    /** Maker-checker: creates an approval request (or refunds directly when approvals are off). */
    public function refund(RefundPaymentRequest $request, Payment $payment): JsonResponse
    {
        Gate::authorize('refund', $payment);
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
