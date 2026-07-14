<?php

declare(strict_types=1);

namespace App\Domain\Payment\Events;

use App\Core\Events\DomainEvent;
use App\Core\Events\StoredInOutbox;
use App\Domain\Payment\Models\Payment;

class PaymentRefundFailed extends DomainEvent implements StoredInOutbox
{
    public function __construct(public readonly Payment $payment, public readonly int $attemptedAmount, public readonly string $reason)
    {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'payment.refund_failed';
    }

    public function payload(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'user_id' => $this->payment->user_id,
            'gateway' => $this->payment->gateway,
            'attempted_amount' => $this->attemptedAmount,
            'reason' => $this->reason,
            'currency' => $this->payment->currency,
        ];
    }
}
