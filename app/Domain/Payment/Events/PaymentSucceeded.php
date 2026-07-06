<?php

declare(strict_types=1);

namespace App\Domain\Payment\Events;

use App\Core\Events\DomainEvent;
use App\Core\Events\StoredInOutbox;
use App\Domain\Payment\Models\Payment;

class PaymentSucceeded extends DomainEvent implements StoredInOutbox
{
    public function __construct(public readonly Payment $payment)
    {
        parent::__construct();
    }

    public function eventName(): string
    {
        return 'payment.succeeded';
    }

    public function payload(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'user_id' => $this->payment->user_id,
            'gateway' => $this->payment->gateway,
            'amount' => $this->payment->amount,
            'currency' => $this->payment->currency,
        ];
    }
}
