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

        $cfgPerPage = config('core.api.per_page', 20);
        $perPageDefault = is_numeric($cfgPerPage) ? (int) $cfgPerPage : 20;
        $cfgMaxPerPage = config('core.api.max_per_page', 100);
        $maxPerPage = is_numeric($cfgMaxPerPage) ? (int) $cfgMaxPerPage : 100;

        $reqPerPageQuery = $request->query('per_page');
        $reqPerPage = is_numeric($reqPerPageQuery) ? (int) $reqPerPageQuery : $perPageDefault;

        $payments = Payment::query()
            ->where('user_id', $this->getAuthenticatedUser($request)->id)
            ->latest()
            ->paginate(min($reqPerPage, $maxPerPage));

        return $this->respond(PaymentResource::collection($payments));
    }

    /**
     * Route carries `idempotent` middleware: send an Idempotency-Key header
     * and retries will replay the original response instead of double-charging.
     */
    public function store(CreatePaymentRequest $request): JsonResponse
    {
        $amountVal = $request->validated('amount');
        $currencyVal = $request->validated('currency');

        $amount = is_numeric($amountVal) ? (string) $amountVal : '0.00';
        $currency = is_string($currencyVal) ? $currencyVal : null;

        $money = Money::fromDecimal($amount, $currency);

        $gatewayVal = $request->validated('gateway');
        $gateway = is_string($gatewayVal) ? $gatewayVal : null;

        $returnUrlVal = $request->validated('return_url');
        $returnUrl = is_string($returnUrlVal) ? $returnUrlVal : null;

        $paymentMethodVal = $request->validated('payment_method');
        $paymentMethod = is_string($paymentMethodVal) ? $paymentMethodVal : null;

        $metadataVal = $request->validated('metadata');
        $metadata = is_array($metadataVal) ? $metadataVal : [];

        $descriptionVal = $request->validated('description');
        $description = is_string($descriptionVal) ? $descriptionVal : null;

        ['payment' => $payment, 'result' => $result] = $this->payments->create(
            user: $this->getAuthenticatedUser($request),
            money: $money,
            gateway: $gateway,
            options: [
                'return_url' => $returnUrl,
                'payment_method' => $paymentMethod,
                'metadata' => $metadata,
            ],
            description: $description,
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

        $amountVal = $request->validated('amount');
        $amount = $amountVal !== null && is_numeric($amountVal)
            ? Money::fromDecimal((string) $amountVal, $payment->currency)
            : null;

        $reasonVal = $request->validated('reason');
        $reason = is_string($reasonVal) ? $reasonVal : null;

        $outcome = $this->payments->requestRefund($payment, $amount, $this->getAuthenticatedUser($request), $reason);

        if ($outcome instanceof ApprovalRequest) {
            return $this->respond(
                new ApprovalRequestResource($outcome),
                __('payments.refund_pending_approval'),
                202,
            );
        }

        return $this->respond(new PaymentResource($outcome), __('payments.refunded'));
    }

    private function getAuthenticatedUser(Request $request): \App\Domain\Auth\Models\User
    {
        $user = $request->user();
        if (! $user instanceof \App\Domain\Auth\Models\User) {
            throw new \App\Core\Exceptions\ApiException(__('auth.unauthorized', ['default' => 'Unauthorized']), status: 401, errorCode: 'unauthorized');
        }

        return $user;
    }
}
