<?php

declare(strict_types=1);

namespace Modules\Payment\Domain\Events;

use Modules\Shared\Domain\Events\DomainEvent;
use Modules\Shared\Domain\Events\StoredInOutbox;
use Modules\Payment\Domain\Models\Payment;

class PaymentRefunded extends DomainEvent implements StoredInOutbox
{
    public function __construct(public readonly Payment $payment, public readonly int $refundedAmount)
    {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'payment.refunded';
    }

    public function payload(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'user_id' => $this->payment->user_id,
            'gateway' => $this->payment->gateway,
            'refunded_amount' => $this->refundedAmount,
            'currency' => $this->payment->currency,
        ];
    }
}
