<?php

declare(strict_types=1);

namespace App\Domain\Payment\Events;

use App\Core\Events\DomainEvent;
use App\Core\Events\StoredInOutbox;
use App\Domain\Payment\Models\Payment;

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
